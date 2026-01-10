<?php

namespace App\Filament\Admin\Resources\GoodsReceipts\Pages;

use App\Filament\Admin\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Jobs\ProcessGoodsReceiptInventory;
use Filament\Actions\Action;
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
            Action::make('print')
                ->label('Cetak Penerimaan')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(function (): void {
                    if (! $this->record?->getKey()) {
                        return;
                    }

                    $this->dispatch(
                        'goods-receipt-print-open',
                        url: route('goods-receipts.print', $this->record),
                        title: $this->record->receipt_number ?? 'Penerimaan Barang'
                    );
                }),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
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
