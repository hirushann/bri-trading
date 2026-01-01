<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PriceListController extends Controller
{
    public function index()
    {
        $products = \App\Models\Product::all();
        return view('price-list', compact('products'));
    }
}
