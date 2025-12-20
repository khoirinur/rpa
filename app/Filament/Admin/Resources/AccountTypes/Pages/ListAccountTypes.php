<?php

namespace App\Filament\Admin\Resources\AccountTypes\Pages;

use App\Filament\Admin\Resources\AccountTypes\AccountTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAccountTypes extends ListRecords
{
    protected static string $resource = AccountTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Tipe Akun'),
        ];
    }

    public function getTitle(): string
    {
        return 'Master Tipe Akun';
    }
}
