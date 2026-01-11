<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function print(Commission $commission)
    {
        return view('commissions.print', compact('commission'));
    }
}
