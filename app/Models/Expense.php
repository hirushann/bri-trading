<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ExpenseCategory;
use App\Models\User;

class Expense extends Model
{
    protected $fillable = [
        'description',
        'amount',
        'date',
        'expense_category_id',
        'receipt_path',
        'user_id',
    ];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
