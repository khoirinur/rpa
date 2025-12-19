<?php

namespace App\Models;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProductImport extends Model
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

    protected array $unitMap = [];

    protected array $existingCodes = [];

    protected array $generatedCodes = [];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function defaultWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
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
            $this->existingCodes = Product::withTrashed()->pluck('code')->map(fn ($code) => strtoupper((string) $code))->all();

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
                'category' => $values[2] ?? null,
                'type' => $values[3] ?? null,
                'unit' => $values[4] ?? null,
            ];
        }

        fclose($stream);

        return array_values(array_filter($rows, fn ($row) => filled($row['name'])));
    }

    protected function importRow(array $row, int $index): void
    {
        $name = $row['name'] ?? null;

        if (blank($name)) {
            throw new RuntimeException('Nama produk tidak boleh kosong.');
        }

        $code = $this->generateCode($row['raw_code'] ?? null, $index);
        $type = $this->resolveType($row['type'] ?? null);
        $categoryId = $this->resolveCategoryId($row['category'] ?? null);
        $unitId = $this->resolveUnitId($row['unit'] ?? null);

        if (! $categoryId) {
            throw new RuntimeException(sprintf('Kategori "%s" belum terdaftar.', $row['category'] ?? '-'));
        }

        Product::updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'type' => $type,
                'product_category_id' => $categoryId,
                'unit_id' => $unitId,
                'default_warehouse_id' => $this->default_warehouse_id,
                'is_active' => true,
                'description' => sprintf('Import dari %s', $row['raw_code'] ?: 'products.csv'),
            ],
        );
    }

    protected function resolveType(?string $label): string
    {
        $normalized = Str::of($label ?? '')
            ->lower()
            ->replace(' ', '_')
            ->replace('-', '_')
            ->value();

        return array_key_exists($normalized, Product::TYPE_OPTIONS)
            ? $normalized
            : 'persediaan';
    }

    protected function resolveCategoryId(?string $label): ?int
    {
        if (blank($label)) {
            return null;
        }

        $this->categoryMap = $this->categoryMap ?: $this->buildCategoryMap();
        $key = $this->normalizeKey($label);

        return $this->categoryMap[$key] ?? null;
    }

    protected function resolveUnitId(?string $label): ?int
    {
        if (blank($label)) {
            return null;
        }

        $this->unitMap = $this->unitMap ?: $this->buildUnitMap();
        $key = $this->normalizeKey($label);

        return $this->unitMap[$key] ?? null;
    }

    protected function buildCategoryMap(): array
    {
        return ProductCategory::query()
            ->get()
            ->flatMap(function (ProductCategory $category): array {
                return [
                    $this->normalizeKey($category->name) => $category->getKey(),
                    $this->normalizeKey($category->code) => $category->getKey(),
                ];
            })
            ->toArray();
    }

    protected function buildUnitMap(): array
    {
        return Unit::query()
            ->get()
            ->flatMap(function (Unit $unit): array {
                return [
                    $this->normalizeKey($unit->name) => $unit->getKey(),
                    $this->normalizeKey($unit->code) => $unit->getKey(),
                ];
            })
            ->toArray();
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
            $number = 1000 + $index;
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

        return sprintf('P-%s', $suffix);
    }

    protected function codeExists(string $code): bool
    {
        $upper = strtoupper($code);

        return in_array($upper, $this->existingCodes, true)
            || in_array($upper, $this->generatedCodes, true);
    }
}
