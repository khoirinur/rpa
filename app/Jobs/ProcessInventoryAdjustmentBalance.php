<?php

namespace App\Jobs;

use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use App\Services\InventoryBalanceService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessInventoryAdjustmentBalance implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $inventoryAdjustmentId)
    {
    }

    public function uniqueId(): string
    {
        return sprintf('inventory-adjustment:%s', $this->inventoryAdjustmentId);
    }

    public function handle(InventoryBalanceService $inventoryBalanceService): void
    {
        $adjustment = InventoryAdjustment::query()
            ->with('items')
            ->find($this->inventoryAdjustmentId);

        if (! $adjustment) {
            return;
        }

        foreach ($adjustment->items as $item) {
            $this->processLineItem($inventoryBalanceService, $adjustment, $item);
        }
    }

    protected function processLineItem(
        InventoryBalanceService $inventoryBalanceService,
        InventoryAdjustment $adjustment,
        InventoryAdjustmentItem $item
    ): void {
        $warehouseId = $item->warehouse_id ?: $adjustment->default_warehouse_id;

        if (! $item->product_id || ! $warehouseId) {
            return;
        }

        $mode = $this->resolveMode($item->adjustment_type);

        $payload = [
            'product_id' => $item->product_id,
            'warehouse_id' => $warehouseId,
            'unit_id' => $item->unit_id,
            'mode' => $mode,
            'quantity' => $mode === InventoryBalanceService::MODE_SET
                ? 0
                : (float) abs($item->quantity ?? 0),
            'target_quantity' => $mode === InventoryBalanceService::MODE_SET
                ? (float) ($item->target_quantity ?? 0)
                : null,
            'unit_cost' => $mode === InventoryBalanceService::MODE_ADDITION
                ? (float) ($item->unit_cost ?? 0)
                : 0,
            'source_type' => InventoryAdjustment::class,
            'source_id' => $adjustment->id,
            'metadata' => array_filter([
                'inventory_adjustment_item_id' => $item->id,
                'inventory_adjustment_number' => $adjustment->adjustment_number,
                'line_notes' => $item->notes,
            ]),
        ];

        try {
            $inventoryBalanceService->adjust($payload);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function resolveMode(?string $adjustmentType): string
    {
        return match ($adjustmentType) {
            InventoryAdjustment::ADJUSTMENT_TYPE_REDUCTION => InventoryBalanceService::MODE_REDUCTION,
            InventoryAdjustment::ADJUSTMENT_TYPE_SET => InventoryBalanceService::MODE_SET,
            default => InventoryBalanceService::MODE_ADDITION,
        };
    }
}
