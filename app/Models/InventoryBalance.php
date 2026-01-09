<?php

namespace App\Models;

use App\Models\ActivityLog;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'unit_id',
        'on_hand_quantity',
        'incoming_quantity',
        'reserved_quantity',
        'available_quantity',
        'average_cost',
        'last_transaction_at',
        'last_source_type',
        'last_source_id',
        'metadata',
    ];

    protected $casts = [
        'on_hand_quantity' => 'decimal:3',
        'incoming_quantity' => 'decimal:3',
        'reserved_quantity' => 'decimal:3',
        'available_quantity' => 'decimal:3',
        'average_cost' => 'decimal:4',
        'metadata' => 'array',
        'last_transaction_at' => 'datetime',
    ];

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

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')
            ->latest('performed_at');
    }
}
