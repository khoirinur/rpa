<?php

namespace App\Filament\Admin\Resources\ChartOfAccounts\Pages;

use App\Filament\Admin\Resources\ChartOfAccounts\ChartOfAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListChartOfAccounts extends ListRecords
{
    protected static string $resource = ChartOfAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Akun'),
        ];
    }

    public function getTitle(): string
    {
        return 'Master COA';
    }
}
