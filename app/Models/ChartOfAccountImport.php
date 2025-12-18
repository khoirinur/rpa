<?php

namespace App\Models;

use App\Models\ChartOfAccount;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ChartOfAccountImport extends Model
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
            'log' => [],
            'imported_rows' => 0,
            'failed_rows' => 0,
        ]);

        $logMessages = [];

        try {
            $rows = $this->readCsv();
            $this->total_rows = count($rows);

            $parentCodes = collect($rows)
                ->pluck('parent_code')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $queue = $rows;
            usort($queue, fn ($a, $b) => strlen($a['code']) <=> strlen($b['code']));

            $remainingIterations = count($queue) * 3;
            $imported = 0;
            $failed = 0;

            while (! empty($queue) && $remainingIterations > 0) {
                $remainingIterations--;
                $row = array_shift($queue);

                if ($row['parent_code'] && ! ChartOfAccount::where('code', $row['parent_code'])->exists()) {
                    $queue[] = $row;
                    continue;
                }

                try {
                    $this->upsertAccount($row, $parentCodes);
                    $imported++;
                } catch (Throwable $th) {
                    $failed++;
                    $logMessages[] = sprintf('[%s] %s', $row['code'], $th->getMessage());
                }
            }

            if (! empty($queue)) {
                foreach ($queue as $row) {
                    $failed++;
                    $logMessages[] = sprintf('[%s] Gagal karena akun induk %s belum ditemukan.', $row['code'], $row['parent_code'] ?: '-');
                }
            }

            $this->update([
                'status' => empty($queue) && $failed === 0 ? self::STATUS_COMPLETED : self::STATUS_FAILED,
                'imported_rows' => $imported,
                'failed_rows' => $failed,
                'finished_at' => now(),
                'log' => $logMessages,
                'total_rows' => $this->total_rows,
            ]);
        } catch (Throwable $exception) {
            $logMessages[] = $exception->getMessage();

            $this->update([
                'status' => self::STATUS_FAILED,
                'finished_at' => now(),
                'log' => $logMessages,
            ]);
        }
    }

    protected function readCsv(): array
    {
        $disk = Storage::disk($this->file_disk);

        if (! $disk->exists($this->file_path)) {
            throw new RuntimeException('Berkas import tidak ditemukan.');
        }

        $rows = [];
        $stream = $disk->readStream($this->file_path);

        if (! $stream) {
            throw new RuntimeException('Gagal membuka berkas untuk dibaca.');
        }

        $headerSkipped = false;

        while (($data = fgetcsv($stream)) !== false) {
            if (! $headerSkipped) {
                $headerSkipped = true;
                continue;
            }

            if (count(array_filter($data, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $rows[] = [
                'code' => trim($data[0] ?? ''),
                'name' => trim($data[1] ?? ''),
                'type_label' => trim($data[2] ?? ''),
                'parent_code' => trim($data[3] ?? ''),
            ];
        }

        fclose($stream);

        return array_values(array_filter($rows, fn ($row) => $row['code'] !== '' && $row['name'] !== ''));
    }

    protected function upsertAccount(array $row, array $parentCodes): void
    {
        $type = $this->resolveType($row['type_label']);
        $normalBalance = in_array($type, ['asset', 'expense'], true) ? 'debit' : 'credit';

        $parentId = null;

        if ($row['parent_code']) {
            $parent = ChartOfAccount::firstWhere('code', $row['parent_code']);

            if (! $parent) {
                throw new RuntimeException("Akun induk {$row['parent_code']} belum terdaftar.");
            }

            $parentId = $parent->id;
        }

        ChartOfAccount::updateOrCreate(
            ['code' => $row['code']],
            [
                'name' => $row['name'],
                'type' => $type,
                'normal_balance' => $normalBalance,
                'parent_id' => $parentId,
                'is_summary' => in_array($row['code'], $parentCodes, true),
                'is_active' => true,
                'opening_balance' => 0,
                'default_warehouse_id' => $this->default_warehouse_id,
                'description' => $row['type_label'],
            ],
        );
    }

    protected function resolveType(string $label): string
    {
        $label = Str::lower($label);

        return match (true) {
            Str::contains($label, ['modal', 'equitas', 'ekuitas']) => 'equity',
            Str::contains($label, ['utang', 'hutang', 'liabilitas', 'pinjaman']) => 'liability',
            Str::contains($label, ['pendapatan', 'laba', 'penghasilan']) => 'revenue',
            Str::contains($label, ['beban', 'hpp', 'rugi']) => 'expense',
            default => 'asset',
        };
    }
}
