<?php

namespace App\Filament\Admin\Resources\CustomerCategories\Pages;

use App\Filament\Admin\Resources\CustomerCategories\CustomerCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerCategory extends CreateRecord
{
    protected static string $resource = CustomerCategoryResource::class;

    public function getTitle(): string
    {
        return 'Tambah Kategori Customer';
    }
}
