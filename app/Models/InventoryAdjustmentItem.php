<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryAdjustmentItem extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'inventory_adjustment_id',
        'product_id',
        'warehouse_id',
        'unit_id',
        'item_code',
        'item_name',
        'adjustment_type',
        'quantity',
        'target_quantity',
        'current_stock_snapshot',
        'unit_cost',
        'total_cost',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'target_quantity' => 'decimal:3',
        'current_stock_snapshot' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class, 'inventory_adjustment_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
