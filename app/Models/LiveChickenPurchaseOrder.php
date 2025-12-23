<?php

namespace App\Models;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LiveChickenPurchaseOrder extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_COMPLETED = 'completed';

    public const DISCOUNT_TYPE_PERCENTAGE = 'percentage';
    public const DISCOUNT_TYPE_AMOUNT = 'amount';

    protected $fillable = [
        'po_number',
        'supplier_id',
        'destination_warehouse_id',
        'product_id',
        'shipping_address',
        'order_date',
        'delivery_date',
        'status',
        'payment_term',
        'payment_term_description',
        'is_tax_inclusive',
        'tax_dpp_type',
        'tax_rate',
        'global_discount_type',
        'global_discount_value',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
        'total_weight_kg',
        'total_quantity_ea',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'delivery_date' => 'date',
        'is_tax_inclusive' => 'boolean',
        'tax_rate' => 'decimal:2',
        'global_discount_value' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'total_weight_kg' => 'decimal:3',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            if (empty($order->po_number)) {
                $order->po_number = static::generateNumber();
            }

            if (empty($order->status)) {
                $order->status = self::STATUS_DRAFT;
            }
        });
    }

    protected static function generateNumber(): string
    {
        $prefix = 'LCP-' . now()->format('ymd');
        $sequence = (self::whereDate('created_at', today())->count() + 1);

        return sprintf('%s-%04d', $prefix, $sequence);
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SUBMITTED => 'Menunggu Persetujuan',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_COMPLETED => 'Selesai',
        ];
    }

    public static function discountTypeOptions(): array
    {
        return [
            self::DISCOUNT_TYPE_AMOUNT => 'Nominal',
            self::DISCOUNT_TYPE_PERCENTAGE => 'Persen',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
