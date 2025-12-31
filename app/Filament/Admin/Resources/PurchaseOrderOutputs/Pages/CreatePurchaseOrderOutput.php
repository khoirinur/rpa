<?php

namespace App\Filament\Admin\Resources\PurchaseOrderOutputs\Pages;

use App\Filament\Admin\Resources\PurchaseOrderOutputs\PurchaseOrderOutputResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrderOutput extends CreateRecord
{
    protected static string $resource = PurchaseOrderOutputResource::class;

    public function getTitle(): string
    {
        return 'Buat Output PO';
    }
}
