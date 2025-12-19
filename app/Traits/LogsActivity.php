<?php

namespace App\Traits;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        foreach (['created', 'updated', 'deleted', 'restored', 'forceDeleted'] as $event) {
            static::$event(function (Model $model) use ($event): void {
                $model->logModelActivity($event);
            });
        }
    }

    protected function logModelActivity(string $event): void
    {
        if (! ActivityLogger::isEnabled()) {
            return;
        }

        $description = match ($event) {
            'created' => 'Membuat data ' . $this->getActivityLabel(),
            'updated' => 'Memperbarui data ' . $this->getActivityLabel(),
            'deleted' => 'Menghapus data ' . $this->getActivityLabel(),
            'restored' => 'Mengembalikan data ' . $this->getActivityLabel(),
            'forceDeleted' => 'Menghapus permanen data ' . $this->getActivityLabel(),
            default => 'Aktivitas pada ' . $this->getActivityLabel(),
        };

        $changes = $event === 'updated' ? $this->getChanges() : $this->getAttributes();

        $revertPayload = $this->makeRevertPayload($event);

        ActivityLogger::log(
            subject: $this,
            actionType: $event,
            description: $description,
            changes: $changes,
            warehouseId: $this->getActivityWarehouseId(),
            revertPayload: $revertPayload,
            metadata: $this->buildActivityMetadata(),
        );
    }

    protected function makeRevertPayload(string $event): ?array
    {
        return match ($event) {
            'created' => [
                'operation' => 'delete',
                'model' => static::class,
                'id' => $this->getKey(),
            ],
            'updated' => [
                'operation' => 'update',
                'model' => static::class,
                'id' => $this->getKey(),
                'attributes' => $this->getOriginal(),
            ],
            'deleted', 'forceDeleted' => method_exists($this, 'restore')
                ? [
                    'operation' => 'restore',
                    'model' => static::class,
                    'id' => $this->getKey(),
                ]
                : null,
            default => null,
        };
    }

    protected function getActivityWarehouseId(): ?int
    {
        $warehouseKey = property_exists($this, 'activityWarehouseField')
            ? $this->activityWarehouseField
            : 'default_warehouse_id';

        return $this->getAttribute($warehouseKey);
    }

    protected function getActivityLabel(): string
    {
        return property_exists($this, 'activityLabel')
            ? $this->activityLabel
            : class_basename($this);
    }

    protected function buildActivityMetadata(): array
    {
        return [];
    }
}
