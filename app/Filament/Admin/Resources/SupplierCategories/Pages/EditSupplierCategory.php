<?php

namespace App\Filament\Admin\Resources\SupplierCategories\Pages;

use App\Filament\Admin\Resources\SupplierCategories\SupplierCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSupplierCategory extends EditRecord
{
    protected static string $resource = SupplierCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return 'Ubah Kategori Supplier';
    }
}
