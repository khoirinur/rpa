<?php

namespace App\Filament\Admin\Resources\LiveChickenPurchaseOrders\Pages;

use App\Filament\Admin\Resources\LiveChickenPurchaseOrders\LiveChickenPurchaseOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLiveChickenPurchaseOrders extends ListRecords
{
    protected static string $resource = LiveChickenPurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
