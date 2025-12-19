<?php

namespace App\Filament\Admin\Resources\CustomerCategories\Pages;

use App\Filament\Admin\Resources\CustomerCategories\CustomerCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomerCategory extends EditRecord
{
    protected static string $resource = CustomerCategoryResource::class;

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
        return 'Ubah Kategori Customer';
    }
}
