<?php

namespace App\Filament\Admin\Resources\InventoryAdjustments\Pages;

use App\Filament\Admin\Resources\InventoryAdjustments\InventoryAdjustmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryAdjustments extends ListRecords
{
    protected static string $resource = InventoryAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Penyesuaian'),
        ];
    }

    public function getTitle(): string
    {
        return 'Penyesuaian Persediaan';
    }
}
