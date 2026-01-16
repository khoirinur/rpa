<?php

namespace App\Filament\Admin\Resources\PurchaseInvoices\Pages;

use App\Filament\Admin\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return 'Ubah Faktur Pembelian';
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['metadata'] = PurchaseInvoiceResource::buildMetadata($data);

        return $data;
    }
}
