<?php

namespace App\Filament\Admin\Resources\AccountTypes\Pages;

use App\Filament\Admin\Resources\AccountTypes\AccountTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAccountType extends CreateRecord
{
    protected static string $resource = AccountTypeResource::class;

    public function getTitle(): string
    {
        return 'Tambah Tipe Akun';
    }
}
