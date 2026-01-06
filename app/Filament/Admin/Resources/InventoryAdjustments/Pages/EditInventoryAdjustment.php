<?php

namespace App\Filament\Admin\Resources\InventoryAdjustments\Pages;

use App\Filament\Admin\Resources\InventoryAdjustments\InventoryAdjustmentResource;
use App\Jobs\ProcessInventoryAdjustmentBalance;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        Log::channel('single')->debug('Inventory adjustment update payload snapshot', [
            'record_id' => $this->record?->getKey(),
            'adjustment_date' => $data['adjustment_date'] ?? null,
            'default_warehouse_id' => $data['default_warehouse_id'] ?? null,
            'items_count' => count($data['items'] ?? []),
        ]);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        Log::channel('single')->info('Inventory adjustment update attempt', [
            'record_id' => $record->getKey(),
        ]);

        try {
            $updatedRecord = parent::handleRecordUpdate($record, $data);

            Log::channel('single')->info('Inventory adjustment update success', [
                'record_id' => $updatedRecord->getKey(),
            ]);

            return $updatedRecord;
        } catch (Throwable $exception) {
            Log::channel('single')->error('Inventory adjustment update failed', [
                'record_id' => $record->getKey(),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    protected function afterSave(): void
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
