<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PriceListController extends Controller
{
    public function index()
    {
        $products = \App\Models\Product::where('stock_quantity', '>', 0)->get();
        return view('price-list', compact('products'));
    }
}
