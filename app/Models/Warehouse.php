<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'slug',
        'location',
        'contact_name',
        'contact_phone',
        'capacity_kg',
        'is_default',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'capacity_kg' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $warehouse): void {
            $warehouse->code = strtoupper((string) $warehouse->code);

            if ($warehouse->isDirty('name') || blank($warehouse->slug)) {
                $warehouse->slug = Str::slug($warehouse->name);
            }
        });
    }
}
