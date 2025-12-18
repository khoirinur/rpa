<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Unit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'slug',
        'measurement_type',
        'decimal_places',
        'is_decimal',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_decimal' => 'boolean',
        'is_active' => 'boolean',
        'decimal_places' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $unit): void {
            $unit->code = strtoupper((string) $unit->code);

            if ($unit->isDirty('name') || blank($unit->slug)) {
                $unit->slug = Str::slug($unit->name);
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
