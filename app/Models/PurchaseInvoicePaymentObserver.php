<?php

namespace App\Models;

use Illuminate\Support\Facades\Log;

class PurchaseInvoicePaymentObserver
{
    public function saving(PurchaseInvoicePayment $payment)
    {
        Log::debug('[DEBUG] PurchaseInvoicePayment saving', [
            'id' => $payment->id,
            'paid_at' => $payment->paid_at,
            'amount' => $payment->amount,
            'is_manual' => $payment->is_manual,
        ]);
    }
}
