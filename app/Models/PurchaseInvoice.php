<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class PurchaseInvoice extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_REVIEW = 'review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_POSTED = 'posted';
    public const STATUS_VOID = 'void';

    public const PAYMENT_STATUS_UNPAID = 'unpaid';
    public const PAYMENT_STATUS_PARTIALLY_PAID = 'partial';
    public const PAYMENT_STATUS_PAID = 'paid';

    public const DISCOUNT_TYPE_AMOUNT = 'amount';
    public const DISCOUNT_TYPE_PERCENTAGE = 'percentage';

    public const REFERENCE_TYPE_PURCHASE_ORDER = 'purchase_order';
    public const REFERENCE_TYPE_GOODS_RECEIPT = 'goods_receipt';

    protected $fillable = [
        'invoice_number',
        'reference_type',
        'reference_number',
        'live_chicken_purchase_order_id',
        'goods_receipt_id',
        'supplier_id',
        'destination_warehouse_id',
        'cash_account_id',
        'status',
        'payment_status',
        'invoice_date',
        'due_date',
        'tax_invoice_number',
        'payment_term',
        'payment_term_description',
        'is_tax_inclusive',
        'tax_dpp_type',
        'tax_rate',
        'global_discount_type',
        'global_discount_value',
        'line_item_total',
        'total_quantity_ea',
        'total_weight_kg',
        'subtotal',
        'discount_total',
        'tax_total',
        'additional_cost_total',
        'grand_total',
        'paid_total',
        'balance_due',
        'additional_costs',
        'attachments',
        'fob_destination',
        'fob_shipping_point',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'is_tax_inclusive' => 'boolean',
        'tax_rate' => 'decimal:2',
        'global_discount_value' => 'decimal:2',
        'total_quantity_ea' => 'decimal:3',
        'total_weight_kg' => 'decimal:3',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'additional_cost_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_total' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'metadata' => 'array',
        'additional_costs' => 'array',
        'attachments' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $invoice): void {
            if (blank($invoice->invoice_number)) {
                $invoice->invoice_number = self::generateNumber();
            }

            if (blank($invoice->invoice_date)) {
                $invoice->invoice_date = now()->toDateString();
            }

            if (blank($invoice->due_date)) {
                $invoice->due_date = now()->toDateString();
            }

            if (blank($invoice->status)) {
                $invoice->status = self::STATUS_DRAFT;
            }

            if (blank($invoice->payment_status)) {
                $invoice->payment_status = self::PAYMENT_STATUS_UNPAID;
            }
        });
    }

    protected static function generateNumber(): string
    {
        $prefix = 'PI-' . now()->format('ymd');

        $latestNumber = self::withTrashed()
            ->where('invoice_number', 'like', $prefix . '-%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $sequence = 1;

        if ($latestNumber) {
            $parts = explode('-', $latestNumber);
            $sequence = ((int) end($parts)) + 1;
        }

        return sprintf('%s-%04d', $prefix, $sequence);
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_REVIEW => 'Menunggu Persetujuan',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_POSTED => 'Sudah Posting',
            self::STATUS_VOID => 'Dibatalkan',
        ];
    }

    public static function paymentStatusOptions(): array
    {
        return [
            self::PAYMENT_STATUS_UNPAID => 'Belum Dibayar',
            self::PAYMENT_STATUS_PARTIALLY_PAID => 'Terbayar Sebagian',
            self::PAYMENT_STATUS_PAID => 'Lunas',
        ];
    }

    public static function discountTypeOptions(): array
    {
        return [
            self::DISCOUNT_TYPE_AMOUNT => 'Nominal',
            self::DISCOUNT_TYPE_PERCENTAGE => 'Persen',
        ];
    }

    public static function referenceTypeOptions(): array
    {
        return [
            self::REFERENCE_TYPE_PURCHASE_ORDER => 'Purchase Order',
            self::REFERENCE_TYPE_GOODS_RECEIPT => 'Goods Receipt',
        ];
    }

    public static function paymentTermOptions(): array
    {
        return [
            'manual' => 'Manual',
            'cod' => 'C.O.D (Cash On Delivery)',
            'net_7' => 'Net 7',
            'net_15' => 'Net 15',
            'net_30' => 'Net 30',
            'net_45' => 'Net 45',
            'net_60' => 'Net 60',
        ];
    }

    public static function taxDppOptions(): array
    {
        return [
            '100' => 'DPP 100%',
            '11/12' => 'DPP 11/12',
            '11/12-10' => 'DPP 11/12 10%',
            '40' => 'DPP 40%',
            '30' => 'DPP 30%',
            '20' => 'DPP 20%',
            '10' => 'DPP 10%',
        ];
    }

    public static function taxRateOptions(): array
    {
        return [
            '0' => '0%',
            '10' => '10%',
            '11' => '11%',
            '12' => '12%',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(LiveChickenPurchaseOrder::class, 'live_chicken_purchase_order_id');
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'cash_account_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchaseInvoicePayment::class);
    }

    public function isOverdue(): bool
    {
        $dueDate = $this->due_date instanceof Carbon
            ? $this->due_date
            : ($this->due_date ? Carbon::parse($this->due_date) : null);

        if (! $dueDate) {
            return false;
        }

        return $dueDate->isPast() && (float) $this->balance_due > 0;
    }
}
