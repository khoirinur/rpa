<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CustomerImport extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'file_name',
        'file_path',
        'file_disk',
        'status',
        'total_rows',
        'imported_rows',
        'failed_rows',
        'fallback_customer_category_id',
        'default_warehouse_id',
        'created_by',
        'started_at',
        'finished_at',
        'log',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'log' => 'array',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'file_disk' => 'public',
    ];

    protected array $categoryMap = [];

    protected array $categoryDefaults = [];

    protected array $existingCodes = [];

    protected array $generatedCodes = [];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public function fallbackCategory(): BelongsTo
    {
        return $this->belongsTo(CustomerCategory::class, 'fallback_customer_category_id');
    }

    public function process(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
            'finished_at' => null,
            'log' => [],
            'imported_rows' => 0,
            'failed_rows' => 0,
        ]);

        $logs = [];

        try {
            $rows = $this->readCsv();
            $this->total_rows = count($rows);
            $this->existingCodes = Customer::withTrashed()
                ->pluck('code')
                ->map(fn ($code) => strtoupper((string) $code))
                ->all();

            $imported = 0;
            $failed = 0;

            foreach ($rows as $index => $row) {
                try {
                    $this->importRow($row, $index);
                    $imported++;
                } catch (Throwable $throwable) {
                    $failed++;
                    $logs[] = sprintf('[%s] %s', $row['raw_code'] ?? 'tanpa kode', $throwable->getMessage());
                }
            }

            $this->update([
                'status' => $failed === 0 ? self::STATUS_COMPLETED : self::STATUS_FAILED,
                'imported_rows' => $imported,
                'failed_rows' => $failed,
                'finished_at' => now(),
                'total_rows' => $this->total_rows,
                'log' => $logs,
            ]);
        } catch (Throwable $exception) {
            $logs[] = $exception->getMessage();

            $this->update([
                'status' => self::STATUS_FAILED,
                'failed_rows' => ($this->failed_rows ?? 0) + 1,
                'finished_at' => now(),
                'log' => $logs,
            ]);
        }
    }

    protected function readCsv(): array
    {
        $disk = Storage::disk($this->file_disk);

        if (! $disk->exists($this->file_path)) {
            throw new RuntimeException('Berkas import tidak ditemukan.');
        }

        $stream = $disk->readStream($this->file_path);

        if (! $stream) {
            throw new RuntimeException('Gagal membaca berkas import.');
        }

        $rows = [];
        $headerSkipped = false;

        while (($data = fgetcsv($stream)) !== false) {
            if (! $headerSkipped) {
                $headerSkipped = true;
                continue;
            }

            $values = array_map(fn ($value) => trim((string) $value), $data);

            if (count(array_filter($values)) === 0) {
                continue;
            }

            $rows[] = [
                'raw_code' => $values[0] ?? null,
                'name' => $values[1] ?? null,
                'contact_phone' => $values[2] ?? null,
                'address_line' => $values[3] ?? null,
                'type' => $values[4] ?? null,
            ];
        }

        fclose($stream);

        return array_values(array_filter($rows, fn ($row) => filled($row['name'])));
    }

    protected function importRow(array $row, int $index): void
    {
        $name = $row['name'] ?? null;

        if (blank($name)) {
            throw new RuntimeException('Nama customer tidak boleh kosong.');
        }

        $code = $this->generateCode($row['raw_code'] ?? null, $index);
        [$categoryId, $categoryWarehouseId] = $this->resolveCategory($row['type'] ?? null);

        if (! $categoryId) {
            if ($this->fallback_customer_category_id) {
                $categoryId = $this->fallback_customer_category_id;
                $categoryWarehouseId = $this->resolveDefaultWarehouseId($categoryId);
            } else {
                throw new RuntimeException(sprintf(
                    'Kategori / tipe "%s" belum terdaftar.',
                    $row['type'] ?: 'tidak diketahui'
                ));
            }
        }

        $defaultWarehouseId = $categoryWarehouseId ?: $this->default_warehouse_id;

        Customer::updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'contact_phone' => $this->normalizePhoneString($row['contact_phone'] ?? null),
                'customer_category_id' => $categoryId,
                'default_warehouse_id' => $defaultWarehouseId,
                'address_line' => $row['address_line'] ?: null,
                'notes' => $this->buildNotes($row),
                'is_active' => true,
            ],
        );
    }

    protected function buildNotes(array $row): ?string
    {
        $type = trim((string) ($row['type'] ?? ''));

        if (blank($type)) {
            return 'Import dari customer.csv';
        }

        return sprintf('Import customer segment %s dari customer.csv', $type);
    }

    protected function resolveCategory(?string $label): array
    {
        $this->hydrateCategoryMap();

        $key = $this->normalizeKey($label);

        if (! $key) {
            return [null, null];
        }

        $categoryId = $this->categoryMap[$key] ?? null;

        return [$categoryId, $categoryId ? $this->resolveDefaultWarehouseId($categoryId) : null];
    }

    protected function hydrateCategoryMap(): void
    {
        if ($this->categoryMap !== []) {
            return;
        }

        $map = [];

        CustomerCategory::query()
            ->select(['id', 'name', 'code', 'default_warehouse_id'])
            ->get()
            ->each(function (CustomerCategory $category) use (&$map): void {
                $this->categoryDefaults[$category->getKey()] = $category->default_warehouse_id;

                $aliases = [
                    $this->normalizeKey($category->name),
                    $this->normalizeKey($category->code),
                ];

                $condensed = $this->normalizeKey(
                    Str::of($category->name)
                        ->lower()
                        ->replace('customer', '')
                        ->value()
                );

                if ($condensed) {
                    $aliases[] = $condensed;
                }

                foreach (array_filter(array_unique($aliases)) as $alias) {
                    $map[$alias] = $category->getKey();
                }
            });

        $this->categoryMap = $map;
    }

    protected function resolveDefaultWarehouseId(int $categoryId): ?int
    {
        return $this->categoryDefaults[$categoryId] ?? null;
    }

    protected function normalizeKey(?string $value): string
    {
        return Str::of($value ?? '')
            ->squish()
            ->lower()
            ->value();
    }

    protected function generateCode(?string $rawCode, int $index): string
    {
        $normalized = preg_replace('/[^0-9]/', '', (string) $rawCode);
        $number = (int) $normalized;

        if ($number <= 0) {
            $number = 2000 + $index;
        }

        $candidate = $this->formatCode($number);

        while ($this->codeExists($candidate)) {
            $number++;
            $candidate = $this->formatCode($number);
        }

        $this->generatedCodes[] = strtoupper($candidate);

        return $candidate;
    }

    protected function formatCode(int $number): string
    {
        $suffix = str_pad((string) $number, 4, '0', STR_PAD_LEFT);

        return sprintf('C-%s', $suffix);
    }

    protected function codeExists(string $code): bool
    {
        $upper = strtoupper($code);

        return in_array($upper, $this->existingCodes, true)
            || in_array($upper, $this->generatedCodes, true);
    }

    protected function normalizePhoneString(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $phones = collect(preg_split('/[;|,\n\r]+/', (string) $value))
            ->map(fn ($phone) => preg_replace('/[^0-9+]/', '', (string) $phone))
            ->filter()
            ->unique()
            ->values();

        return $phones->isEmpty() ? null : $phones->implode(';');
    }
}
