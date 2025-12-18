<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    public const CATEGORY_OPTIONS = [
        'hasil_panen' => 'Hasil Panen',
        'live_bird' => 'Live Bird',
        'produk' => 'Produk',
        'umum' => 'Umum',
    ];

    public const TYPE_OPTIONS = [
        'persediaan' => 'Persediaan',
        'jasa' => 'Jasa',
        'non_persediaan' => 'Non-Persediaan',
    ];

    protected static array $unitOptionsCache = [];

    protected $fillable = [
        'code',
        'name',
        'slug',
        'type',
        'unit',
        'category',
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

            if ($product->isDirty('name') || blank($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    public function defaultWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public static function categoryOptions(): array
    {
        return self::CATEGORY_OPTIONS;
    }

    public static function typeOptions(): array
    {
        return self::TYPE_OPTIONS;
    }

    public static function unitOptions(): array
    {
        if (empty(self::$unitOptionsCache)) {
            self::$unitOptionsCache = Unit::query()
                ->orderBy('name')
                ->pluck('name', 'code')
                ->toArray();
        }

        return self::$unitOptionsCache;
    }
}
