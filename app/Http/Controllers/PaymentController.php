<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function print(Payment $payment)
    {
        $payment->load('invoice.order.customer');
        return view('payments.print', compact('payment'));
    }
}
