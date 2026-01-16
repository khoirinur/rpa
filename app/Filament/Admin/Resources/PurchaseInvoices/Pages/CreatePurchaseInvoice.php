<?php

namespace App\Filament\Admin\Resources\PurchaseInvoices\Pages;

use App\Filament\Admin\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseInvoice extends CreateRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    public function getTitle(): string
    {
        return 'Tambah Faktur Pembelian';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['metadata'] = PurchaseInvoiceResource::buildMetadata($data);

        return $data;
    }
}
