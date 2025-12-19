<?php

namespace App\Filament\Admin\Resources\SupplierImports\Pages;

use App\Filament\Admin\Resources\SupplierImports\SupplierImportResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSupplierImport extends EditRecord
{
    protected static string $resource = SupplierImportResource::class;

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
        return 'Detail Import Supplier';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['file_path'] = $this->record->file_path;

        return $data;
    }
}
