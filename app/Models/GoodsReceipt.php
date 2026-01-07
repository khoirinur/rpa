<?php

namespace App\Models;

use App\Jobs\ProcessGoodsReceiptInventory;
use App\Models\ChartOfAccount;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class GoodsReceipt extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_INSPECTED = 'inspected';
    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'receipt_number',
        'live_chicken_purchase_order_id',
        'supplier_id',
        'destination_warehouse_id',
        'delivery_address',
        'expected_delivery_date',
        'received_at',
        'status',
        'supplier_delivery_note_number',
        'vehicle_plate_number',
        'arrival_temperature_c',
        'arrival_inspector_name',
        'arrival_checks',
        'arrival_notes',
        'attachments',
        'additional_costs',
        'total_item_count',
        'total_received_weight_kg',
        'total_received_quantity_ea',
        'loss_weight_kg',
        'loss_percentage',
        'loss_quantity_ea',
        'notes',
        'metadata',
        'posted_at',
    ];

    protected $casts = [
        'expected_delivery_date' => 'date',
        'received_at' => 'datetime',
        'arrival_checks' => 'array',
        'attachments' => 'array',
        'additional_costs' => 'array',
        'metadata' => 'array',
        'arrival_temperature_c' => 'decimal:2',
        'total_received_weight_kg' => 'decimal:3',
        'total_received_quantity_ea' => 'decimal:3',
        'loss_weight_kg' => 'decimal:3',
        'loss_percentage' => 'decimal:2',
        'loss_quantity_ea' => 'decimal:3',
        'posted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $receipt): void {
            if (blank($receipt->receipt_number)) {
                $receipt->receipt_number = self::generateNumber();
            }

            if (blank($receipt->status)) {
                $receipt->status = self::STATUS_DRAFT;
            }
        });

        static::created(fn (self $receipt) => $receipt->syncChartOfAccounts(+1));

        static::deleted(function (self $receipt): void {
            if ($receipt->isForceDeleting() && $receipt->getOriginal('deleted_at') !== null) {
                return;
            }

            $receipt->syncChartOfAccounts(-1);

            DB::afterCommit(function () use ($receipt): void {
                ProcessGoodsReceiptInventory::dispatchSync($receipt->getKey(), true);
            });
        });

        static::restored(function (self $receipt): void {
            $receipt->syncChartOfAccounts(+1);

            DB::afterCommit(function () use ($receipt): void {
                ProcessGoodsReceiptInventory::dispatchSync($receipt->getKey());
            });
        });
    }

    protected static function generateNumber(): string
    {
        $prefix = 'GR-' . now()->format('ymd');

        $latestNumber = self::withTrashed()
            ->where('receipt_number', 'like', $prefix . '-%')
            ->orderByDesc('receipt_number')
            ->value('receipt_number');

        $sequence = 1;

        if ($latestNumber) {
            $parts = explode('-', $latestNumber);
            $lastSegment = end($parts);
            $sequence = ((int) $lastSegment) + 1;
        }

        return sprintf('%s-%04d', $prefix, $sequence);
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_INSPECTED => 'Sudah Diperiksa',
            self::STATUS_POSTED => 'Sudah Posting',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(LiveChickenPurchaseOrder::class, 'live_chicken_purchase_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    protected function syncChartOfAccounts(int $direction): void
    {
        foreach ($this->additional_costs ?? [] as $cost) {
            $code = $cost['coa_reference'] ?? null;
            $amount = (float) ($cost['amount'] ?? 0);

            if ($code && $amount > 0) {
                ChartOfAccount::where('code', $code)
                    ->increment('opening_balance', $direction * $amount);
            }
        }
    }

}
