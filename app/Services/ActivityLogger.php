<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ActivityLogger
{
    protected static bool $enabled = true;

    public static function isEnabled(): bool
    {
        return self::$enabled && ! app()->runningInConsole();
    }

    public static function withoutLogging(callable $callback): mixed
    {
        $previous = self::$enabled;
        self::$enabled = false;

        try {
            return $callback();
        } finally {
            self::$enabled = $previous;
        }
    }

    public static function log(
        Model $subject,
        string $actionType,
        string $description,
        array $changes = [],
        ?int $warehouseId = null,
        ?array $revertPayload = null,
        array $metadata = [],
        ?string $module = null,
    ): ?ActivityLog {
        if (! self::isEnabled()) {
            return null;
        }

        return ActivityLog::create([
            'user_id' => auth()->id(),
            'module' => $module ?: Str::headline(class_basename($subject)),
            'action_type' => $actionType,
            'description' => $description,
            'subject_id' => $subject->getKey(),
            'subject_type' => $subject->getMorphClass(),
            'warehouse_id' => $warehouseId,
            'changes' => $changes ?: null,
            'revert_payload' => $revertPayload,
            'metadata' => $metadata ?: null,
            'performed_at' => now(),
        ]);
    }
}
