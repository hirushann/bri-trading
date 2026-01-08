<?php

namespace App\Observers;

use App\Models\Payment;

class PaymentObserver
{
    /**
     * Handle the Payment "saved" event (created and updated).
     */
    public function saved(Payment $payment): void
    {
        $this->updateInvoice($payment);
    }

    /**
     * Handle the Payment "created" event.
     */
    public function created(Payment $payment): void
    {
        // Handled by saved
        if ($payment->sales_rep_id && $payment->amount > 0) {
            \App\Models\Commission::create([
                'user_id' => $payment->sales_rep_id,
                'payment_id' => $payment->id,
                'amount' => $payment->amount * 0.10, // 10% commission
                'status' => 'pending',
            ]);
        }
    }

    /**
     * Handle the Payment "updated" event.
     */
    public function updated(Payment $payment): void
    {
        // Handled by saved
    }

    /**
     * Handle the Payment "deleted" event.
     */
    public function deleted(Payment $payment): void
    {
        $this->updateInvoice($payment);
    }

    protected function updateInvoice(Payment $payment): void
    {
        $invoice = $payment->invoice;
        if ($invoice) {
             // Reload relations to get fresh sum
             $invoice->load('payments');
             $totalPaid = $invoice->payments()->sum('amount');
             $totalWaived = $invoice->payments()->sum('discount'); // Add this column sum
             
             $balanceDue = $invoice->total_amount - ($totalPaid + $totalWaived);
             
             $status = 'unpaid';
             if ($balanceDue <= 0) {
                 $balanceDue = 0; // Prevent negative
                 $status = 'paid';
             } elseif ($balanceDue < $invoice->total_amount) {
                 $status = 'partial';
             }
             
             // Avoid infinite loops by checkUpdated/Quietly if needed, 
             // but InvoiceObserver only listens to confirmed orders, so unsafe here?
             // Actually Invoice doesn't have an observer that reacts to its own update to create loops.
             // OrderObserver reacts to Order updates. 
             // We are safe.
             
             $invoice->updateQuietly([
                 'balance_due' => $balanceDue,
                 'status' => $status,
             ]);
        }
    }
}
