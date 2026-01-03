<?php

namespace App\Filament\Admin\Resources\LiveChickenPurchaseOrders\Pages;

use App\Filament\Admin\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Admin\Resources\LiveChickenPurchaseOrders\LiveChickenPurchaseOrderResource;
use App\Models\GoodsReceipt;
use App\Models\LiveChickenPurchaseOrder;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditLiveChickenPurchaseOrder extends EditRecord
{
    protected static string $resource = LiveChickenPurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Cetak PO')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('live-chicken-purchase-orders.print', $this->record))
                ->openUrlInNewTab(true),
            Action::make('process-to-goods-receipt')
                ->label('Proses ke Penerimaan')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('success')
                ->visible(fn (): bool => $this->canProcessToGoodsReceipt())
                ->url(fn (): string => GoodsReceiptResource::getUrl('create') . '?live_chicken_purchase_order_id=' . $this->record->getKey())
                ->openUrlInNewTab(false),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return LiveChickenPurchaseOrderResource::hydrateLineItemsFromMetadata($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return LiveChickenPurchaseOrderResource::persistLineItemsIntoMetadata($data);
    }

    protected function canProcessToGoodsReceipt(): bool
    {
        if (! $this->record || $this->record->status !== LiveChickenPurchaseOrder::STATUS_APPROVED) {
            return false;
        }

        return ! GoodsReceipt::withTrashed()
            ->where('live_chicken_purchase_order_id', $this->record->getKey())
            ->exists();
    }
}
