<?php

namespace App\Filament\Admin\Resources\SupplierCategories\Pages;

use App\Filament\Admin\Resources\SupplierCategories\SupplierCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupplierCategory extends CreateRecord
{
    protected static string $resource = SupplierCategoryResource::class;

    public function getTitle(): string
    {
        return 'Tambah Kategori Supplier';
    }
}
