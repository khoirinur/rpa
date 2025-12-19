<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'contact_email',
        'contact_phone',
        'customer_category_id',
        'default_warehouse_id',
        'address_line',
        'city',
        'province',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $customer): void {
            $customer->code = strtoupper((string) $customer->code);
            $customer->contact_phone = self::normalizeContactPhones($customer->contact_phone);
        });
    }

    protected static function normalizeContactPhones(null|string|array $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        $phones = collect(is_array($value) ? $value : preg_split('/[;,\n]+/', (string) $value))
            ->map(fn ($phone) => preg_replace('/[^0-9+]/', '', (string) $phone))
            ->filter()
            ->unique()
            ->values();

        return $phones->isEmpty() ? null : $phones->implode(';');
    }

    public function customerCategory(): BelongsTo
    {
        return $this->belongsTo(CustomerCategory::class);
    }

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getContactPhoneListAttribute(): array
    {
        if (blank($this->contact_phone)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(';', $this->contact_phone))));
    }
}
