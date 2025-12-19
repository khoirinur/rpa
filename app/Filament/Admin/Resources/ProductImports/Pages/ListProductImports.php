<?php

namespace App\Filament\Admin\Resources\ProductImports\Pages;

use App\Filament\Admin\Resources\ProductImports\ProductImportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductImports extends ListRecords
{
    protected static string $resource = ProductImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Import Produk Baru'),
        ];
    }

    public function getTitle(): string
    {
        return 'Riwayat Import Produk';
    }
}
