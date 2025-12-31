<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrderOutput extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY = 'ready';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'live_chicken_purchase_order_id',
        'warehouse_id',
        'printed_by_user_id',
        'document_number',
        'document_title',
        'document_date',
        'status',
        'layout_template',
        'document_sections',
        'attachments',
        'metadata',
        'notes',
        'printed_at',
    ];

    protected $casts = [
        'document_date' => 'date',
        'document_sections' => 'array',
        'attachments' => 'array',
        'metadata' => 'array',
        'printed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $output): void {
            if (empty($output->document_number)) {
                $output->document_number = self::generateNumber();
            }

            if (empty($output->document_date)) {
                $output->document_date = now();
            }

            if (empty($output->status)) {
                $output->status = self::STATUS_DRAFT;
            }
        });
    }

    protected static function generateNumber(): string
    {
        $prefix = 'OPO-' . now()->format('ymd');

        $latestNumber = self::withTrashed()
            ->where('document_number', 'like', $prefix . '-%')
            ->orderByDesc('document_number')
            ->value('document_number');

        $sequence = 1;

        if ($latestNumber) {
            $parts = explode('-', $latestNumber);
            $lastSegment = end($parts);
            $sequence = ((int) $lastSegment) + 1;
        }

        return sprintf('%s-%04d', $prefix, $sequence);
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_READY => 'Siap Cetak',
            self::STATUS_PUBLISHED => 'Final',
        ];
    }

    public static function layoutTemplateOptions(): array
    {
        return [
            'standard' => 'Standar (Header + Tabel)',
            'detailed' => 'Detail + Ringkasan Biaya',
            'minimal' => 'Minimalis',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(LiveChickenPurchaseOrder::class, 'live_chicken_purchase_order_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by_user_id');
    }
}
