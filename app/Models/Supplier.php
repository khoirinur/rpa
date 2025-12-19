<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'npwp',
        'supplier_category_id',
        'default_warehouse_id',
        'owner_name',
        'contact_phone',
        'contact_email',
        'bank_account_name',
        'bank_name',
        'bank_account_number',
        'address_line',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $supplier): void {
            $supplier->code = strtoupper((string) $supplier->code);
            $supplier->contact_phone = self::normalizeContactPhones($supplier->contact_phone);
        });
    }

    protected static function normalizeContactPhones(null|string|array $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        $phones = collect(is_array($value) ? $value : preg_split('/[;,\n]+/', (string) $value))
            ->map(fn ($phone) => preg_replace('/[^0-9+]/', '', (string) $phone))
            ->map(fn ($phone) => ltrim($phone))
            ->filter()
            ->unique()
            ->values();

        return $phones->isEmpty() ? null : $phones->implode(';');
    }

    public function supplierCategory(): BelongsTo
    {
        return $this->belongsTo(SupplierCategory::class);
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
