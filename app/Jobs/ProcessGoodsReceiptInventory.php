<?php

namespace App\Jobs;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Services\InventoryBalanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class ProcessGoodsReceiptInventory implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $goodsReceiptId,
        public bool $resetToZero = false,
    ) {
    }

    public function uniqueId(): string
    {
        return sprintf('goods-receipt:%s:%s', $this->goodsReceiptId, $this->resetToZero ? 'reset' : 'sync');
    }

    public function handle(InventoryBalanceService $inventoryBalanceService): void
    {
        $receipt = GoodsReceipt::withTrashed()
            ->with([
                'items' => fn ($query) => $query->withTrashed()->with('product'),
            ])
            ->find($this->goodsReceiptId);

        if (! $receipt) {
            return;
        }

        foreach ($receipt->items as $item) {
            $this->syncItemInventory($inventoryBalanceService, $receipt, $item);
        }
    }

    protected function syncItemInventory(
        InventoryBalanceService $inventoryBalanceService,
        GoodsReceipt $receipt,
        GoodsReceiptItem $item
    ): void {
        $shouldReset = $this->resetToZero || $item->trashed();
        $targetQuantity = $shouldReset ? 0.0 : $this->resolveEffectiveQuantity($item);

        $metadata = $item->metadata ?? [];
        $appliedQuantity = (float) Arr::get($metadata, 'inventory_balance.applied_quantity', 0);
        $delta = round($targetQuantity - $appliedQuantity, 6);

        if (abs($delta) < 0.0005) {
            if ($shouldReset && $appliedQuantity !== 0.0) {
                Arr::set($metadata, 'inventory_balance.applied_quantity', 0.0);
                Arr::set($metadata, 'inventory_balance.last_synced_at', now()->toISOString());
                $item->forceFill(['metadata' => $metadata])->saveQuietly();
            }

            return;
        }

        $warehouseId = $item->warehouse_id ?: $receipt->destination_warehouse_id;

        if (! $item->product_id || ! $warehouseId) {
            return;
        }

        $mode = $delta >= 0
            ? InventoryBalanceService::MODE_ADDITION
            : InventoryBalanceService::MODE_REDUCTION;

        $payload = [
            'product_id' => $item->product_id,
            'warehouse_id' => $warehouseId,
            'unit_id' => $item->product?->unit_id,
            'mode' => $mode,
            'quantity' => abs($delta),
            'source_type' => GoodsReceipt::class,
            'source_id' => $receipt->getKey(),
            'metadata' => array_filter([
                'goods_receipt_item_id' => $item->getKey(),
                'goods_receipt_number' => $receipt->receipt_number,
                'unit' => $item->unit,
            ]),
        ];

        if ($payload['quantity'] <= 0) {
            return;
        }

        try {
            $inventoryBalanceService->adjust($payload);

            Arr::set($metadata, 'inventory_balance.applied_quantity', $targetQuantity);
            Arr::set($metadata, 'inventory_balance.last_synced_at', now()->toISOString());
            $item->forceFill(['metadata' => $metadata])->saveQuietly();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function resolveEffectiveQuantity(GoodsReceiptItem $item): float
    {
        $unit = Str::lower((string) ($item->unit ?? ''));
        $receivedQty = (float) ($item->received_quantity ?? 0);
        $receivedWeight = (float) ($item->received_weight_kg ?? 0);

        return match ($unit) {
            'ekor' => $receivedQty,
            'kg' => $receivedWeight,
            default => $receivedQty > 0 ? $receivedQty : $receivedWeight,
        };
    }
}
