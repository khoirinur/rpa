<?php

namespace App\Filament\Admin\Resources\SupplierImports\Pages;

use App\Filament\Admin\Resources\SupplierImports\SupplierImportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSupplierImports extends ListRecords
{
    protected static string $resource = SupplierImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Import Supplier Baru'),
        ];
    }

    public function getTitle(): string
    {
        return 'Riwayat Import Supplier';
    }
}
