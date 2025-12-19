<?php

namespace App\Filament\Admin\Resources\SupplierCategories\Pages;

use App\Filament\Admin\Resources\SupplierCategories\SupplierCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSupplierCategories extends ListRecords
{
    protected static string $resource = SupplierCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Kategori Supplier'),
        ];
    }

    public function getTitle(): string
    {
        return 'Master Kategori Supplier';
    }
}
