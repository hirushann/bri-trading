<?php

namespace App\Observers;

use App\Models\CommissionPayment;

class CommissionPaymentObserver
{
    /**
     * Handle the CommissionPayment "created" event.
     */
    public function created(CommissionPayment $commissionPayment): void
    {
        $amountLeft = $commissionPayment->amount;

        $commissions = \App\Models\Commission::where('user_id', $commissionPayment->user_id)
            ->whereIn('status', ['pending', 'partially_paid'])
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($commissions as $commission) {
            if ($amountLeft <= 0) {
                break;
            }

            $due = $commission->due_amount;

            if ($amountLeft >= $due) {
                // Fully pay this commission
                $commission->update([
                    'paid_amount' => $commission->amount,
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
                $amountLeft -= $due;
            } else {
                // Partially pay this commission
                $commission->update([
                    'paid_amount' => $commission->paid_amount + $amountLeft,
                    'status' => 'partially_paid',
                ]);
                $amountLeft = 0;
            }
        }
    }

    /**
     * Handle the CommissionPayment "updated" event.
     */
    public function updated(CommissionPayment $commissionPayment): void
    {
        //
    }

    /**
     * Handle the CommissionPayment "deleted" event.
     */
    public function deleted(CommissionPayment $commissionPayment): void
    {
        //
    }

    /**
     * Handle the CommissionPayment "restored" event.
     */
    public function restored(CommissionPayment $commissionPayment): void
    {
        //
    }

    /**
     * Handle the CommissionPayment "force deleted" event.
     */
    public function forceDeleted(CommissionPayment $commissionPayment): void
    {
        //
    }
}
