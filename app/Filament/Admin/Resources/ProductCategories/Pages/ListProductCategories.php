<?php

namespace App\Filament\Admin\Resources\ProductCategories\Pages;

use App\Filament\Admin\Resources\ProductCategories\ProductCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductCategories extends ListRecords
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Kategori'),
        ];
    }

    public function getTitle(): string
    {
        return 'Master Kategori Produk';
    }
}
