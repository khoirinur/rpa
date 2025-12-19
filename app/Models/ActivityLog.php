<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ActivityLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'module',
        'action_type',
        'description',
        'subject_id',
        'subject_type',
        'warehouse_id',
        'changes',
        'revert_payload',
        'metadata',
        'performed_at',
        'reverted_at',
        'reverted_by',
        'revert_reason',
    ];

    protected $casts = [
        'changes' => 'array',
        'revert_payload' => 'array',
        'metadata' => 'array',
        'performed_at' => 'datetime',
        'reverted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function revertedBy()
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function isReverted(): bool
    {
        return ! is_null($this->reverted_at);
    }

    public function canRevert(): bool
    {
        return ! $this->isReverted() && ! empty($this->revert_payload);
    }

    public function revert(?string $reason = null, ?User $actor = null): void
    {
        if (! $this->canRevert()) {
            throw new RuntimeException('Aktivitas ini tidak dapat dibatalkan.');
        }

        $payload = $this->revert_payload;
        $operation = Arr::get($payload, 'operation');
        $modelClass = Arr::get($payload, 'model', $this->subject_type);
        $recordId = Arr::get($payload, 'id', $this->subject_id);

        if (! $modelClass || ! $recordId) {
            throw new RuntimeException('Payload pembatalan tidak valid.');
        }

        DB::transaction(function () use ($operation, $modelClass, $recordId, $payload, $reason, $actor): void {
            /** @var Model|null $modelInstance */
            $modelInstance = $modelClass::withTrashed()->find($recordId);

            if (! $modelInstance) {
                throw new RuntimeException('Record yang ingin dibatalkan tidak ditemukan.');
            }

            match ($operation) {
                'delete' => $modelInstance->delete(),
                'restore' => method_exists($modelInstance, 'restore')
                    ? $modelInstance->restore()
                    : throw new RuntimeException('Model tidak mendukung restore.'),
                'update' => $modelInstance->fill(Arr::get($payload, 'attributes', []))->save(),
                default => throw new RuntimeException('Operasi pembatalan tidak dikenal.'),
            };

            $this->update([
                'reverted_at' => now(),
                'reverted_by' => $actor?->getKey() ?? auth()->id(),
                'revert_reason' => $reason,
            ]);
        });
    }

    protected function module(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ?: Str::headline(class_basename($this->subject_type ?? 'Aktivitas')),
        );
    }

    public static function formatActionType(?string $action): string
    {
        return $action ? Str::headline($action) : 'Tidak Diketahui';
    }

    public static function actionTypeOptions(): array
    {
        return self::query()
            ->select('action_type')
            ->distinct()
            ->orderBy('action_type')
            ->pluck('action_type')
            ->mapWithKeys(fn ($type) => [$type => self::formatActionType($type)])
            ->all();
    }
}
