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
     * The one place invoice balance and status are worked out: what is owed (nothing once the order is cancelled,
     * less returns otherwise) minus paid and waived, ignoring bounced cheques.
     * Also moves the order to completed / partially_paid / confirmed to match, and keeps rep commission in step.
     */
    public function recalculateBalance(bool $syncOrderStatus = true): void
    {
        $order = $this->order()->first();
        $cancelled = $order?->status === 'cancelled';
        $balanceDue = round($this->owed($order) - $this->received(), 2);

        $status = match (true) {
            $cancelled => 'cancelled',
            $balanceDue <= 0 => 'paid',
            $balanceDue < $this->total_amount => 'partial',
            default => 'unpaid',
        };

        $this->updateQuietly(['balance_due' => max(0, $balanceDue), 'status' => $status]);

        if ($syncOrderStatus && $order && ! in_array($order->status, ['cancelled', 'returned'])) {
            $orderStatus = match ($status) {
                'paid' => 'completed',
                'partial' => 'partially_paid',
                default => 'confirmed',
            };

            if ($orderStatus !== $order->status) {
                $order->update(['status' => $orderStatus]);
            }
        }

        $this->payments()->get()->each->syncCommission();
    }

    /** What the customer has to pay: nothing for a cancelled order, otherwise the total less returns. */
    public function owed(?Order $order = null): float
    {
        $order ??= $this->order()->first();
        if ($order?->status === 'cancelled') {
            return 0;
        }

        return (float) $this->total_amount - (float) ($order ? $order->returns()->sum('amount') : 0);
    }

    /** Paid plus settlement discounts, ignoring bounced cheques. */
    public function received(): float
    {
        $counted = $this->payments()->where('status', '!=', 'bounced');

        return (float) $counted->sum('amount') + (float) $counted->sum('discount');
    }

    /** Money to give back to the customer: overpaid, or paid and then returned or cancelled. */
    public function credit(): float
    {
        return max(0, round($this->received() - $this->owed(), 2));
    }
}
