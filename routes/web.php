<?php

use App\Http\Controllers\LiveChickenPurchaseOrderPrintController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function (): void {
    Route::get('live-chicken-purchase-orders/{liveChickenPurchaseOrder}/print', LiveChickenPurchaseOrderPrintController::class)
        ->name('live-chicken-purchase-orders.print');
});
