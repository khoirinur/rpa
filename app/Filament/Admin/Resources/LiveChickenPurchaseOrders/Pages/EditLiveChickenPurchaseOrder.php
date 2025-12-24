<?php

namespace App\Filament\Admin\Resources\LiveChickenPurchaseOrders\Pages;

use App\Filament\Admin\Resources\LiveChickenPurchaseOrders\LiveChickenPurchaseOrderResource;
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
}
