<?php

namespace App\Filament\Admin\Resources\CustomerImports\Pages;

use App\Filament\Admin\Resources\CustomerImports\CustomerImportResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomerImport extends EditRecord
{
    protected static string $resource = CustomerImportResource::class;

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
        return 'Detail Import Customer';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['file_path'] = $this->record->file_path;

        return $data;
    }
}
