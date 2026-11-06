<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The one place invoice balance and status are worked out: total minus paid, waived and returned, ignoring bounced cheques.
     * Also moves the order to completed / partially_paid / confirmed to match.
     */
    public function recalculateBalance(bool $syncOrderStatus = true): void
    {
        $counted = $this->payments()->where('status', '!=', 'bounced');
        $returned = $this->order ? $this->order->returns()->sum('amount') : 0;
        $balanceDue = $this->total_amount - ($counted->sum('amount') + $counted->sum('discount') + $returned);

        $status = 'unpaid';
        if ($balanceDue <= 0) {
            $balanceDue = 0;
            $status = 'paid';
        } elseif ($balanceDue < $this->total_amount) {
            $status = 'partial';
        }

        $this->updateQuietly(['balance_due' => $balanceDue, 'status' => $status]);

        if ($syncOrderStatus && $this->order && ! in_array($this->order->status, ['cancelled', 'returned'])) {
            $orderStatus = match ($status) {
                'paid' => 'completed',
                'partial' => 'partially_paid',
                default => 'confirmed',
            };

            if ($orderStatus !== $this->order->status) {
                $this->order->update(['status' => $orderStatus]);
            }
        }
    }
}
