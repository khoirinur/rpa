<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceiptItem extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'goods_receipt_id',
        'product_id',
        'warehouse_id',
        'item_code',
        'item_name',
        'unit',
        'ordered_quantity',
        'ordered_weight_kg',
        'received_quantity',
        'received_weight_kg',
        'loss_quantity',
        'loss_weight_kg',
        'tolerance_percentage',
        'is_returned',
        'status',
        'qc_notes',
        'metadata',
    ];

    protected $casts = [
        'ordered_quantity' => 'decimal:3',
        'ordered_weight_kg' => 'decimal:3',
        'received_quantity' => 'decimal:3',
        'received_weight_kg' => 'decimal:3',
        'loss_quantity' => 'decimal:3',
        'loss_weight_kg' => 'decimal:3',
        'tolerance_percentage' => 'decimal:2',
        'is_returned' => 'boolean',
        'metadata' => 'array',
    ];

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
