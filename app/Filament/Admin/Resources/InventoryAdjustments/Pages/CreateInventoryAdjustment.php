<?php

namespace App\Filament\Admin\Resources\InventoryAdjustments\Pages;

use App\Filament\Admin\Resources\InventoryAdjustments\InventoryAdjustmentResource;
use App\Jobs\ProcessInventoryAdjustmentBalance;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryAdjustment extends CreateRecord
{
    protected static string $resource = InventoryAdjustmentResource::class;

    public function getTitle(): string
    {
        return 'Tambah Penyesuaian Persediaan';
    }

    protected function afterCreate(): void
    {
        if ($this->record?->getKey()) {
            ProcessInventoryAdjustmentBalance::dispatch($this->record->getKey());
        }
    }
}
