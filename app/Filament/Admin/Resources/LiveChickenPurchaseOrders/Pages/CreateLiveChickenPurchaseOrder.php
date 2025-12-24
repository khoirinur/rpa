<?php

namespace App\Filament\Admin\Resources\LiveChickenPurchaseOrders\Pages;

use App\Filament\Admin\Resources\LiveChickenPurchaseOrders\LiveChickenPurchaseOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLiveChickenPurchaseOrder extends CreateRecord
{
    protected static string $resource = LiveChickenPurchaseOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return LiveChickenPurchaseOrderResource::persistLineItemsIntoMetadata($data);
    }
}
