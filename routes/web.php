<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/admin/invoices/{invoice}/print', [App\Http\Controllers\InvoiceController::class, 'print'])->name('invoices.print');
Route::get('/price-list', [\App\Http\Controllers\PriceListController::class, 'index'])->name('price-list');
Route::get('/admin/payments/{payment}/print', [App\Http\Controllers\PaymentController::class, 'print'])->name('payments.print');
Route::get('/admin/commissions/{commission}/print', [App\Http\Controllers\CommissionController::class, 'print'])->name('commissions.print');

Route::get('/admin/reports/outstanding-payments/print', function (\Illuminate\Http\Request $request) {
    $query = App\Models\Invoice::query()
        ->where('balance_due', '>', 0)
        ->latest('issued_date');

    if ($from = $request->input('from')) {
        $query->whereDate('issued_date', '>=', $from);
    }
    if ($until = $request->input('until')) {
        $query->whereDate('issued_date', '<=', $until);
    }
    if ($place = $request->input('place')) {
        $query->whereHas('order.customer', function ($q) use ($place) {
            $q->where('address', 'like', "%{$place}%");
        });
    }

    $invoices = $query->with(['order.customer', 'payments'])->get();

    return view('reports.print-outstanding-payments', compact('invoices'));
})->name('reports.outstanding-payments.print');

Route::get('/admin/reports/income/print', function (\Illuminate\Http\Request $request) {
    $query = App\Models\Payment::query()->latest('transaction_date');

    if ($from = $request->input('from')) {
        $query->whereDate('transaction_date', '>=', $from);
    }
    if ($until = $request->input('until')) {
        $query->whereDate('transaction_date', '<=', $until);
    }

    $payments = $query->with(['invoice.order.customer'])->get();

    return view('reports.print-income-report', compact('payments'));
})->name('reports.income.print');

Route::get('/admin/reports/expenses/print', function (\Illuminate\Http\Request $request) {
    $query = App\Models\Expense::query()->latest('date');

    if ($from = $request->input('from')) {
        $query->whereDate('date', '>=', $from);
    }
    if ($until = $request->input('until')) {
        $query->whereDate('date', '<=', $until);
    }
    if ($categoryId = $request->input('category')) {
        $query->where('expense_category_id', $categoryId);
    }

    $expenses = $query->with(['category', 'user'])->get();

    return view('reports.print-expense-report', compact('expenses'));
})->name('reports.expenses.print');
