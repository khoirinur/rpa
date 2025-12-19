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
