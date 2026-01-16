<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoicePayment extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    public const TYPE_DOWN_PAYMENT = 'down_payment';
    public const TYPE_INSTALLMENT = 'installment';
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const METHOD_CASH = 'cash';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_CHEQUE = 'cheque';
    public const METHOD_CREDIT_CARD = 'credit_card';
    public const METHOD_E_WALLET = 'e_wallet';

    protected $fillable = [
        'purchase_invoice_id',
        'account_id',
        'payment_type',
        'payment_method',
        'reference_number',
        'paid_at',
        'amount',
        'is_manual',
        'attachments',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'paid_at' => 'date',
        'amount' => 'decimal:2',
        'is_manual' => 'boolean',
        'attachments' => 'array',
        'metadata' => 'array',
    ];

    public static function typeOptions(): array
    {
        return [
            self::TYPE_DOWN_PAYMENT => 'Uang Muka',
            self::TYPE_INSTALLMENT => 'Cicilan',
            self::TYPE_ADJUSTMENT => 'Penyesuaian',
        ];
    }

    public static function methodOptions(): array
    {
        return [
            self::METHOD_CASH => 'Tunai',
            self::METHOD_BANK_TRANSFER => 'Transfer Bank',
            self::METHOD_CHEQUE => 'Cek/Giro',
            self::METHOD_CREDIT_CARD => 'Kartu Kredit',
            self::METHOD_E_WALLET => 'Dompet Digital',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
