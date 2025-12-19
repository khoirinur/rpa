<?php

namespace App\Filament\Admin\Resources\CustomerImports\Pages;

use App\Filament\Admin\Resources\CustomerImports\CustomerImportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomerImports extends ListRecords
{
    protected static string $resource = CustomerImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Import Customer Baru'),
        ];
    }

    public function getTitle(): string
    {
        return 'Riwayat Import Customer';
    }
}
