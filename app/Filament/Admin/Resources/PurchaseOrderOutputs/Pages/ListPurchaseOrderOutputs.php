<?php

namespace App\Filament\Admin\Resources\PurchaseOrderOutputs\Pages;

use App\Filament\Admin\Resources\PurchaseOrderOutputs\PurchaseOrderOutputResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseOrderOutputs extends ListRecords
{
    protected static string $resource = PurchaseOrderOutputResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Output PO'),
        ];
    }

    public function getTitle(): string
    {
        return 'Output PO';
    }
}
