<?php

namespace App\Filament\Admin\Resources\ProductImports\Pages;

use App\Filament\Admin\Resources\ProductImports\ProductImportResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditProductImport extends EditRecord
{
    protected static string $resource = ProductImportResource::class;

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
        return 'Detail Import Produk';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['file_path'] = $this->record->file_path;

        return $data;
    }
}
