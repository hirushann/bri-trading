<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function printShippingLabel(\App\Models\Order $order)
    {
        $order->load(['customer']);
        return view('orders.shipping-label', compact('order'));
    }
}
