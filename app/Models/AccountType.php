<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AccountType extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public const CATEGORY_OPTIONS = ChartOfAccount::TYPE_OPTIONS;

    protected $fillable = [
        'code',
        'name',
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
        static::saving(function (self $type): void {
            $type->code = strtoupper(Str::of((string) $type->code)->replace(' ', '-')->value());
        });
    }

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public static function categoryOptions(): array
    {
        return self::CATEGORY_OPTIONS;
    }
}
