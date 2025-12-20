<?php

namespace App\Models;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public const TYPE_OPTIONS = [
        'akumulasi_penyusutan' => 'Akumulasi Penyusutan',
        'aset_lainnya' => 'Aset Lainnya',
        'aset_lancar_lainnya' => 'Aset Lancar Lainnya',
        'aset_tetap' => 'Aset Tetap',
        'beban' => 'Beban',
        'beban_lainnya' => 'Beban Lainnya',
        'beban_pokok_penjualan' => 'Beban Pokok Penjualan',
        'kas_bank' => 'Kas & Bank',
        'liabilitas_jangka_panjang' => 'Liabilitas Jangka Panjang',
        'liabilitas_jangka_pendek' => 'Liabilitas Jangka Pendek',
        'modal' => 'Modal',
        'pendapatan' => 'Pendapatan',
        'pendapatan_lainnya' => 'Pendapatan Lainnya',
        'persediaan' => 'Persediaan',
        'piutang_usaha' => 'Piutang Usaha',
        'utang_usaha' => 'Utang Usaha',
    ];

    protected $fillable = [
        'code',
        'name',
        'type',
        'parent_id',
        'level',
        'is_summary',
        'is_active',
        'opening_balance',
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

    public function scopeSummary($query)
    {
        return $query->where('is_summary', true);
    }

    public static function typeOptions(): array
    {
        return self::TYPE_OPTIONS;
    }
}
