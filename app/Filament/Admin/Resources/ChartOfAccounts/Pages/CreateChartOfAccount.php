<?php

namespace App\Filament\Admin\Resources\ChartOfAccounts\Pages;

use App\Filament\Admin\Resources\ChartOfAccounts\ChartOfAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChartOfAccount extends CreateRecord
{
    protected static string $resource = ChartOfAccountResource::class;

    public function getTitle(): string
    {
        return 'Tambah Akun';
    }
}
