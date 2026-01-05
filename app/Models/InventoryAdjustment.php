<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryAdjustment extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    public const ADJUSTMENT_TYPE_ADDITION = 'addition';
    public const ADJUSTMENT_TYPE_REDUCTION = 'reduction';
    public const ADJUSTMENT_TYPE_SET = 'set';

    protected $fillable = [
        'adjustment_number',
        'adjustment_date',
        'default_warehouse_id',
        'adjustment_account_id',
        'total_addition_value',
        'total_reduction_value',
        'total_set_value',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
        'total_addition_value' => 'decimal:2',
        'total_reduction_value' => 'decimal:2',
        'total_set_value' => 'decimal:2',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $adjustment): void {
            if (blank($adjustment->adjustment_number)) {
                $adjustment->adjustment_number = self::generateNumber();
            }

            if (blank($adjustment->adjustment_date)) {
                $adjustment->adjustment_date = now()->toDateString();
            }
        });
    }

    public static function adjustmentTypeOptions(): array
    {
        return [
            self::ADJUSTMENT_TYPE_ADDITION => 'Penambahan',
            self::ADJUSTMENT_TYPE_REDUCTION => 'Pengurangan',
            self::ADJUSTMENT_TYPE_SET => 'Atur Stok',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentItem::class);
    }

    public function adjustmentAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'adjustment_account_id');
    }

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    protected static function generateNumber(): string
    {
        $prefix = 'IA-' . now()->format('ymd');

        $latestNumber = self::withTrashed()
            ->where('adjustment_number', 'like', $prefix . '-%')
            ->orderByDesc('adjustment_number')
            ->value('adjustment_number');

        $sequence = 1;

        if ($latestNumber) {
            $parts = explode('-', $latestNumber);
            $sequence = ((int) end($parts)) + 1;
        }

        return sprintf('%s-%04d', $prefix, $sequence);
    }
}
