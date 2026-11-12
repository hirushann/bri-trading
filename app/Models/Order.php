<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Order $order) {
            // Undo returns first, so each item's stock is put back only once.
            $order->returns->each->delete();
            $order->items->each->delete();
        });
    }

    protected $guarded = [];

    public const STATUSES = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'partially_paid' => 'Partially Paid',
        'completed' => 'Completed',
        'delivered' => 'Delivered',
        'returned' => 'Returned',
        'cancelled' => 'Cancelled',
    ];

    public const STATUS_COLORS = [
        'pending' => 'gray',
        'confirmed' => 'info',
        'partially_paid' => 'warning',
        'completed' => 'success',
        'delivered' => 'success',
        'returned' => 'danger',
        'cancelled' => 'danger',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    /** Items total less the order discount, worked out from the saved items so the browser's figure is never trusted. */
    public function calculateTotal(): float
    {
        $subtotal = (float) $this->items()->sum(DB::raw('unit_price * quantity'));
        $discount = $this->discount_type === 'percentage' ? $subtotal * min(100, (float) $this->discount) / 100 : (float) $this->discount;

        return round(max(0, $subtotal - $discount), 2);
    }

    public function syncTotal(): void
    {
        $total = $this->calculateTotal();
        if (abs($total - (float) $this->total_amount) >= 0.01) {
            $this->update(['total_amount' => $total]);
        }
    }

    public function isFullyReturned(): bool
    {
        $sold = $this->items()->sum('quantity');

        return $sold > 0 && OrderReturnItem::whereIn('order_item_id', $this->items()->select('id'))->sum('quantity') >= $sold;
    }
}
