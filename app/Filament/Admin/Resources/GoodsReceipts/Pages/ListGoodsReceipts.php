<?php

namespace App\Filament\Admin\Resources\GoodsReceipts\Pages;

use App\Filament\Admin\Resources\GoodsReceipts\GoodsReceiptResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGoodsReceipts extends ListRecords
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Penerimaan'),
        ];
    }

    public function getTitle(): string
    {
        return 'Penerimaan Barang';
    }
}
