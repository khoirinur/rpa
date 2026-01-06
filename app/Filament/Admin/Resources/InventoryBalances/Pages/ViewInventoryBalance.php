<?php

namespace App\Filament\Admin\Resources\InventoryBalances\Pages;

use App\Filament\Admin\Resources\InventoryBalances\InventoryBalanceResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Arr;

class ViewInventoryBalance extends ViewRecord
{
    protected static string $resource = InventoryBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return 'Detail Saldo Persediaan';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return Arr::except($data, ['metadata']);
    }
}
