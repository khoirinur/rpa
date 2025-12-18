<?php

namespace App\Filament\Admin\Resources\ChartOfAccountImports\Pages;

use App\Filament\Admin\Resources\ChartOfAccountImports\ChartOfAccountImportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListChartOfAccountImports extends ListRecords
{
    protected static string $resource = ChartOfAccountImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Import COA Baru'),
        ];
    }

    public function getTitle(): string
    {
        return 'Riwayat Import COA';
    }
}
