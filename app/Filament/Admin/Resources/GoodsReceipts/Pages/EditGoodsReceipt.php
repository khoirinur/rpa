<?php

namespace App\Filament\Admin\Resources\GoodsReceipts\Pages;

use App\Filament\Admin\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Jobs\ProcessGoodsReceiptInventory;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditGoodsReceipt extends EditRecord
{
    protected static string $resource = GoodsReceiptResource::class;

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
        return 'Ubah Penerimaan Barang';
    }

    protected function afterSave(): void
    {
        if (! $this->record?->getKey()) {
            return;
        }

        DB::afterCommit(function (): void {
            ProcessGoodsReceiptInventory::dispatchSync($this->record->getKey());
        });
    }
}
