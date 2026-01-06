<?php

namespace App\Filament\Admin\Resources\InventoryBalances\Pages;

use App\Filament\Admin\Resources\InventoryBalances\InventoryBalanceResource;
use Filament\Resources\Pages\ListRecords;

class ListInventoryBalances extends ListRecords
{
    protected static string $resource = InventoryBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return 'Saldo Persediaan';
    }
}
