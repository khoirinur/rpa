<?php

namespace App\Filament\Admin\Resources\InventoryAdjustments\Pages;

use App\Filament\Admin\Resources\InventoryAdjustments\InventoryAdjustmentResource;
use App\Jobs\ProcessInventoryAdjustmentBalance;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditInventoryAdjustment extends EditRecord
{
    protected static string $resource = InventoryAdjustmentResource::class;

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
        return 'Ubah Penyesuaian Persediaan';
    }

    protected function afterSave(): void
    {
        if ($this->record?->getKey()) {
            ProcessInventoryAdjustmentBalance::dispatch($this->record->getKey());
        }
    }
}
