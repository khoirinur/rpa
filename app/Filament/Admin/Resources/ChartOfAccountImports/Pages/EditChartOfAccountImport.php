<?php

namespace App\Filament\Admin\Resources\ChartOfAccountImports\Pages;

use App\Filament\Admin\Resources\ChartOfAccountImports\ChartOfAccountImportResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditChartOfAccountImport extends EditRecord
{
    protected static string $resource = ChartOfAccountImportResource::class;

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
        return 'Detail Import COA';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['file_path'] = $this->record->file_path;

        return $data;
    }
}
