<?php

namespace App\Services;

use App\Models\InventoryBalance;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryBalanceService
{
    public const MODE_ADDITION = 'addition';
    public const MODE_REDUCTION = 'reduction';
    public const MODE_SET = 'set';

    /**
     * @param  array{product_id:int,warehouse_id:int,unit_id?:int|null,quantity?:float|null,mode?:string,unit_cost?:float|null,reserved_delta?:float|null,incoming_delta?:float|null,source_type?:string|null,source_id?:int|null,metadata?:array|null,target_quantity?:float|null}  $payload
     */
    public function adjust(array $payload): InventoryBalance
    {
        $productId = Arr::get($payload, 'product_id');
        $warehouseId = Arr::get($payload, 'warehouse_id');
        $unitId = Arr::get($payload, 'unit_id');
        $mode = Arr::get($payload, 'mode', self::MODE_ADDITION);
        $quantity = (float) Arr::get($payload, 'quantity', 0);
        $targetQuantity = (float) Arr::get($payload, 'target_quantity', 0);

        if (! $productId || ! $warehouseId) {
            throw new InvalidArgumentException('product_id dan warehouse_id wajib diisi.');
        }

        if (! in_array($mode, [self::MODE_ADDITION, self::MODE_REDUCTION, self::MODE_SET], true)) {
            throw new InvalidArgumentException('Mode penyesuaian stok tidak valid.');
        }

        if ($mode !== self::MODE_SET && $quantity === 0.0) {
            throw new InvalidArgumentException('Quantity wajib diisi untuk mode penambahan atau pengurangan.');
        }

        if ($mode === self::MODE_SET && $targetQuantity < 0) {
            throw new InvalidArgumentException('Qty target tidak boleh negatif.');
        }

        return DB::transaction(function () use ($productId, $warehouseId, $unitId, $quantity, $mode, $payload, $targetQuantity): InventoryBalance {
            $balance = InventoryBalance::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('unit_id', $unitId)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $balance = InventoryBalance::create([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'unit_id' => $unitId,
                ]);
            }

            $previousOnHand = (float) $balance->on_hand_quantity;

            $this->applyQuantityMutation($balance, $mode, $quantity, $targetQuantity);
            $this->applyIncomingDelta($balance, (float) Arr::get($payload, 'incoming_delta', 0));
            $this->applyReservedDelta($balance, (float) Arr::get($payload, 'reserved_delta', 0));
            $this->applyAverageCost(
                $balance,
                (float) Arr::get($payload, 'unit_cost', 0),
                $quantity,
                $mode,
                $previousOnHand,
            );

            $balance->last_transaction_at = now();
            $balance->last_source_type = Arr::get($payload, 'source_type');
            $balance->last_source_id = Arr::get($payload, 'source_id');
            $balance->metadata = Arr::get($payload, 'metadata') ?: null;
            $balance->available_quantity = max($balance->on_hand_quantity - $balance->reserved_quantity, 0);
            $balance->save();

            return $balance->refresh();
        });
    }

    protected function applyQuantityMutation(InventoryBalance $balance, string $mode, float $quantity, float $targetQuantity = 0): void
    {
        if ($mode === self::MODE_SET) {
            $balance->on_hand_quantity = max($targetQuantity, 0);
            return;
        }

        if ($mode === self::MODE_ADDITION) {
            $balance->on_hand_quantity = max($balance->on_hand_quantity + abs($quantity), 0);
            return;
        }

        $balance->on_hand_quantity = max($balance->on_hand_quantity - abs($quantity), 0);
    }

    protected function applyIncomingDelta(InventoryBalance $balance, float $delta): void
    {
        if ($delta === 0.0) {
            return;
        }

        $balance->incoming_quantity = max($balance->incoming_quantity + $delta, 0);
    }

    protected function applyReservedDelta(InventoryBalance $balance, float $delta): void
    {
        if ($delta === 0.0) {
            return;
        }

        $balance->reserved_quantity = max($balance->reserved_quantity + $delta, 0);
    }

    protected function applyAverageCost(
        InventoryBalance $balance,
        float $unitCost,
        float $quantity,
        string $mode,
        float $previousOnHand,
    ): void {
        if ($mode !== self::MODE_ADDITION || $unitCost <= 0 || $quantity <= 0) {
            return;
        }

        $existingQty = max($previousOnHand, 0);
        $existingValue = $existingQty * (float) $balance->getOriginal('average_cost', $balance->average_cost);
        $newValue = $quantity * $unitCost;
        $totalQty = $existingQty + $quantity;

        if ($totalQty <= 0) {
            $balance->average_cost = $unitCost;
            return;
        }

        $balance->average_cost = round(($existingValue + $newValue) / $totalQty, 4);
    }
}
