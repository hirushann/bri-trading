<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/admin/invoices/{invoice}/print', [App\Http\Controllers\InvoiceController::class, 'print'])->name('invoices.print');
Route::get('/price-list', [\App\Http\Controllers\PriceListController::class, 'index'])->name('price-list');
Route::get('/admin/payments/{payment}/print', [App\Http\Controllers\PaymentController::class, 'print'])->name('payments.print');
