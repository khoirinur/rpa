<?php

namespace App\Filament\Admin\Resources\CustomerCategories\Pages;

use App\Filament\Admin\Resources\CustomerCategories\CustomerCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomerCategories extends ListRecords
{
    protected static string $resource = CustomerCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Kategori Customer'),
        ];
    }

    public function getTitle(): string
    {
        return 'Master Kategori Customer';
    }
}
