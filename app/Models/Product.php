<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected static array $warehouseIdCache = [];

    public const TYPE_OPTIONS = [
        'persediaan' => 'Persediaan',
        'jasa' => 'Jasa',
        'non_persediaan' => 'Non-Persediaan',
    ];

    protected $fillable = [
        'code',
        'name',
        'type',
        'product_category_id',
        'unit_id',
        'default_warehouse_id',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $product): void {
            $product->code = strtoupper((string) $product->code);
        });

        static::saved(function (self $product): void {
            self::ensureInventoryBalances($product);
        });
    }

    protected static function ensureInventoryBalances(self $product): void
    {
        $warehouseIds = self::resolveWarehouseIdsForBalance($product);

        if (empty($warehouseIds)) {
            return;
        }

        foreach ($warehouseIds as $warehouseId) {
            InventoryBalance::firstOrCreate([
                'product_id' => $product->getKey(),
                'warehouse_id' => $warehouseId,
                'unit_id' => $product->unit_id,
            ]);
        }
    }

    protected static function resolveWarehouseIdsForBalance(self $product): array
    {
        if (empty(self::$warehouseIdCache)) {
            self::$warehouseIdCache = Warehouse::query()->pluck('id')->all();
        }

        $ids = self::$warehouseIdCache;

        if ($product->default_warehouse_id) {
            $ids[] = $product->default_warehouse_id;
        }

        $ids = array_filter(array_unique($ids));

        return array_values($ids);
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public static function typeOptions(): array
    {
        return self::TYPE_OPTIONS;
    }
}
