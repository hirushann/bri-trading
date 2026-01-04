<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\Order;

class OrderObserver
{
    /**
     * Handle the Order "updated" event.
     */
    /**
     * Handle the Order "created" event.
     */
    public function creating(Order $order): void
    {
        // Auto-generate reference BRI-0001
        $latestOrder = Order::latest('id')->first();
        $nextId = $latestOrder ? $latestOrder->id + 1 : 1;
        $order->reference = 'BRI-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    }
    public function created(Order $order): void
    {
        if (! $order->invoice) {
            $order->invoice()->create([
                'invoice_number' => 'BRI-INV-' . str_pad($order->id, 4, '0', STR_PAD_LEFT),
                'issued_date' => now(),
                'total_amount' => $order->total_amount,
                'balance_due' => $order->total_amount,
                'status' => 'unpaid',
            ]);
        }
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Sync invoice total if order total changes
        if ($order->isDirty('total_amount')) {
            $invoice = $order->invoice;
            if ($invoice) {
                $invoice->update([
                    'total_amount' => $order->total_amount,
                    'balance_due' => $order->total_amount - $invoice->payments->sum('amount'),
                ]);
            }
        }
    }
    
    // Potential: handle status cancelled -> restock all items logic here? 
    // Leaving it simple for now as requested.
}
