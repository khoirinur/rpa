<?php

use App\Http\Controllers\PurchaseOrderOutputPrintController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function (): void {
    Route::get('documents/purchase-order-outputs/{purchaseOrderOutput}', PurchaseOrderOutputPrintController::class)
        ->name('purchase-order-outputs.print');
});
