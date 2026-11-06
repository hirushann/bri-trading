<?php

namespace App\Observers;

use App\Models\Commission;
use App\Models\Invoice;
use App\Models\Payment;

class PaymentObserver
{
    public function creating(Payment $payment): void
    {
        if (! $payment->sales_rep_id && $payment->invoice_id) {
            $payment->sales_rep_id = $payment->invoice->order->sales_rep_id ?? null;
        }
    }

    /**
     * Handle the Payment "saved" event (created and updated).
     */
    public function saved(Payment $payment): void
    {
        // Look up fresh: a loaded $payment->invoice still points at the old invoice after a move.
        Invoice::find($payment->invoice_id)?->recalculateBalance();

        // Payment moved to another invoice: the old one no longer has this money.
        if ($payment->wasChanged('invoice_id') && $payment->getOriginal('invoice_id')) {
            Invoice::find($payment->getOriginal('invoice_id'))?->recalculateBalance();
        }

        $this->syncCommission($payment);
    }

    /**
     * Runs before the row is gone, because the database then sets commissions.payment_id to null.
     */
    public function deleting(Payment $payment): void
    {
        $this->unpaidCommission($payment)?->delete();
    }

    public function deleted(Payment $payment): void
    {
        $payment->invoice?->recalculateBalance();
    }

    /**
     * 10% commission for the rep while the payment counts (not bounced).
     * Only commissions nothing has been paid on are changed or removed; paid ones are left as they are.
     */
    protected function syncCommission(Payment $payment): void
    {
        $existing = Commission::where('payment_id', $payment->id)->first();
        $earns = $payment->sales_rep_id && $payment->amount > 0 && $payment->status !== 'bounced';

        if (! $existing) {
            if ($earns) {
                Commission::create([
                    'user_id' => $payment->sales_rep_id,
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount * 0.10,
                    'status' => 'pending',
                ]);
            }

            return;
        }

        // Older commissions can be marked paid with paid_amount still 0, so check both.
        if ($existing->status !== 'pending' || $existing->paid_amount > 0) {
            return;
        }

        if ($earns) {
            $existing->update(['user_id' => $payment->sales_rep_id, 'amount' => $payment->amount * 0.10]);
        } else {
            $existing->delete();
        }
    }

    protected function unpaidCommission(Payment $payment): ?Commission
    {
        return Commission::where('payment_id', $payment->id)->where('status', 'pending')->where('paid_amount', 0)->first();
    }
}
