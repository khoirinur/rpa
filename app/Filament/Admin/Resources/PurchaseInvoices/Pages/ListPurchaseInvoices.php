<?php

namespace App\Filament\Admin\Resources\PurchaseInvoices\Pages;

use App\Filament\Admin\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseInvoices extends ListRecords
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Faktur'),
        ];
    }

    public function getTitle(): string
    {
        return 'Faktur Pembelian';
    }
}
