<?php

namespace App\Filament\Admin\Resources\InventoryAdjustments\Pages;

use App\Filament\Admin\Resources\InventoryAdjustments\InventoryAdjustmentResource;
use App\Jobs\ProcessInventoryAdjustmentBalance;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateInventoryAdjustment extends CreateRecord
{
    protected static string $resource = InventoryAdjustmentResource::class;

    public function getTitle(): string
    {
        return 'Tambah Penyesuaian Persediaan';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        Log::channel('single')->debug('Inventory adjustment create payload snapshot', [
            'adjustment_date' => $data['adjustment_date'] ?? null,
            'default_warehouse_id' => $data['default_warehouse_id'] ?? null,
            'items_count' => count($data['items'] ?? []),
        ]);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        Log::channel('single')->info('Inventory adjustment create attempt');

        try {
            $record = parent::handleRecordCreation($data);

            Log::channel('single')->info('Inventory adjustment create success', [
                'record_id' => $record->getKey(),
            ]);

            return $record;
        } catch (Throwable $exception) {
            Log::channel('single')->error('Inventory adjustment create failed', [
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
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
