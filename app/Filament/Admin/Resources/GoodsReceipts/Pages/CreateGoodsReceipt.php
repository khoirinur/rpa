<?php

namespace App\Filament\Admin\Resources\GoodsReceipts\Pages;

use App\Filament\Admin\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\LiveChickenPurchaseOrder;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateGoodsReceipt extends CreateRecord
{
    protected static string $resource = GoodsReceiptResource::class;

    public function getTitle(): string
    {
        return 'Buat Penerimaan Barang';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->guardPurchaseOrderNotProcessed($data['live_chicken_purchase_order_id'] ?? null);

        $data = $this->ensurePurchaseOrderDependencies($data);
        $data['received_at'] = $data['received_at'] ?? now();
        $data['additional_costs'] = $this->sanitizeAdditionalCosts($data['additional_costs'] ?? []);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->applyAdditionalCostsToChartOfAccounts($this->record->additional_costs ?? []);
    }

    protected function ensurePurchaseOrderDependencies(array $data): array
    {
        $purchaseOrderId = $data['live_chicken_purchase_order_id'] ?? null;

        if (! $purchaseOrderId) {
            return $data;
        }

        if (filled($data['supplier_id'] ?? null) && filled($data['destination_warehouse_id'] ?? null)) {
            return $data;
        }

        $po = LiveChickenPurchaseOrder::query()
            ->select(['id', 'supplier_id', 'destination_warehouse_id'])
            ->find($purchaseOrderId);

        if (! $po) {
            return $data;
        }

        $data['supplier_id'] = $data['supplier_id'] ?? $po->supplier_id;
        $data['destination_warehouse_id'] = $data['destination_warehouse_id'] ?? $po->destination_warehouse_id;

        return $data;
    }

    protected function sanitizeAdditionalCosts(array $costs): array
    {
        if (empty($costs)) {
            return [];
        }

        return collect($costs)
            ->map(function (array $cost): array {
                return [
                    'name' => $cost['name'] ?? null,
                    'coa_reference' => $cost['coa_reference'] ?? null,
                    'amount' => (float) ($cost['amount'] ?? 0),
                    'notes' => $cost['notes'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    protected function applyAdditionalCostsToChartOfAccounts(array $costs): void
    {
        if (empty($costs)) {
            return;
        }

        foreach ($costs as $cost) {
            $code = $cost['coa_reference'] ?? null;
            $amount = (float) ($cost['amount'] ?? 0);

            if (! $code || $amount === 0.0) {
                continue;
            }

            ChartOfAccount::query()
                ->where('code', $code)
                ->increment('opening_balance', $amount);
        }
    }

    protected function guardPurchaseOrderNotProcessed(?int $purchaseOrderId): void
    {
        if (! $purchaseOrderId) {
            return;
        }

        $alreadyProcessed = GoodsReceipt::withTrashed()
            ->where('live_chicken_purchase_order_id', $purchaseOrderId)
            ->exists();

        if ($alreadyProcessed) {
            throw ValidationException::withMessages([
                'live_chicken_purchase_order_id' => 'PO ini sudah memiliki Penerimaan Barang.',
            ]);
        }
    }
}
