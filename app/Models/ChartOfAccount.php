<?php

namespace App\Models;

use App\Models\Warehouse;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public const TYPE_OPTIONS = [
        'asset' => 'Aset',
        'liability' => 'Liabilitas',
        'equity' => 'Ekuitas',
        'revenue' => 'Pendapatan',
        'expense' => 'Biaya',
    ];

    public const NORMAL_BALANCE_OPTIONS = [
        'debit' => 'Debit',
        'credit' => 'Kredit',
    ];

    protected $fillable = [
        'code',
        'name',
        'type',
        'normal_balance',
        'parent_id',
        'level',
        'is_summary',
        'is_active',
        'opening_balance',
        'default_warehouse_id',
        'description',
    ];

    protected $casts = [
        'is_summary' => 'boolean',
        'is_active' => 'boolean',
        'opening_balance' => 'decimal:2',
        'level' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $account): void {
            $account->code = strtoupper((string) $account->code);

            $parentLevel = 0;

            if ($account->parent_id) {
                $parentLevel = (int) optional($account->parent()->first())->level;
            }

            $account->level = $parentLevel + 1;
        });
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function defaultWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public function scopeSummary($query)
    {
        return $query->where('is_summary', true);
    }

    public static function typeOptions(): array
    {
        return self::TYPE_OPTIONS;
    }

    public static function normalBalanceOptions(): array
    {
        return self::NORMAL_BALANCE_OPTIONS;
    }
}
