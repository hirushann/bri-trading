<?php

namespace App\Observers;

use App\Models\Commission;
use App\Models\CommissionPayment;

/**
 * A commission payment pays off the rep's oldest unpaid commissions. Editing or deleting it takes that money back
 * off the most recently paid commissions first.
 */
class CommissionPaymentObserver
{
    public function created(CommissionPayment $commissionPayment): void
    {
        self::pay($commissionPayment->user_id, (float) $commissionPayment->amount);
    }

    public function updated(CommissionPayment $commissionPayment): void
    {
        if ($commissionPayment->wasChanged(['amount', 'user_id'])) {
            self::unpay($commissionPayment->getOriginal('user_id'), (float) $commissionPayment->getOriginal('amount'));
            self::pay($commissionPayment->user_id, (float) $commissionPayment->amount);
        }
    }

    public function deleted(CommissionPayment $commissionPayment): void
    {
        self::unpay($commissionPayment->user_id, (float) $commissionPayment->amount);
    }

    protected static function pay(?int $userId, float $amount): void
    {
        $commissions = Commission::where('user_id', $userId)
            ->whereIn('status', ['pending', 'partially_paid'])
            ->orderBy('created_at')->orderBy('id')
            ->get();

        foreach ($commissions as $commission) {
            if ($amount <= 0) {
                break;
            }

            $take = min($amount, $commission->due_amount);
            $paid = round($commission->paid_amount + $take, 2);
            $full = $paid >= $commission->amount;
            $commission->update([
                'paid_amount' => $paid,
                'status' => $full ? 'paid' : 'partially_paid',
                'paid_at' => $full ? now() : null,
            ]);
            $amount -= $take;
        }
    }

    protected static function unpay(?int $userId, float $amount): void
    {
        $commissions = Commission::where('user_id', $userId)
            ->where('paid_amount', '>', 0)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get();

        foreach ($commissions as $commission) {
            if ($amount <= 0) {
                break;
            }

            $back = min($amount, (float) $commission->paid_amount);
            $paid = round($commission->paid_amount - $back, 2);
            $commission->update([
                'paid_amount' => $paid,
                'status' => $paid <= 0 ? 'pending' : 'partially_paid',
                'paid_at' => null,
            ]);
            $amount -= $back;
        }
    }
}
