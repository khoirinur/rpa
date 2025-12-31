<?php

namespace App\Filament\Admin\Resources\PurchaseOrderOutputs\Pages;

use App\Filament\Admin\Resources\PurchaseOrderOutputs\PurchaseOrderOutputResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseOrderOutput extends EditRecord
{
    protected static string $resource = PurchaseOrderOutputResource::class;

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
        return 'Ubah Output PO';
    }
}
