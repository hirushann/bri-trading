<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function print(Invoice $invoice)
    {
        $invoice->load(['order.customer', 'order.items.product', 'payments']);
        return view('invoices.print', compact('invoice'));
    }
}
