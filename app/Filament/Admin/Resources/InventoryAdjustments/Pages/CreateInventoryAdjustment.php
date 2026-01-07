<?php

namespace App\Filament\Admin\Resources\InventoryAdjustments\Pages;

use App\Filament\Admin\Resources\InventoryAdjustments\InventoryAdjustmentResource;
use App\Jobs\ProcessInventoryAdjustmentBalance;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateInventoryAdjustment extends CreateRecord
{
    protected static string $resource = InventoryAdjustmentResource::class;

    public function getTitle(): string
    {
        return 'Tambah Penyesuaian Persediaan';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        return parent::handleRecordCreation($data);
    }

    protected function afterCreate(): void
    {
        if (! $this->record?->getKey()) {
            return;
        }

        $recordId = $this->record->getKey();

        DB::afterCommit(static function () use ($recordId): void {
            ProcessInventoryAdjustmentBalance::dispatchSync($recordId);
        });
    }
}
