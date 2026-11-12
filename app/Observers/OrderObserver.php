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
        // Set temporary reference to satisfy unique constraint
        $order->reference = 'TEMP-' . uniqid();
    }
    public function updating(Order $order): void
    {
        // A changed discount changes the total; items saved in the same form re-sync it afterwards.
        if ($order->isDirty(['discount', 'discount_type'])) {
            $order->total_amount = $order->calculateTotal();
        }
    }

    public function created(Order $order): void
    {
        // Update with final reference based on ID
        $order->updateQuietly([
            'reference' => 'BRI-' . str_pad($order->id, 4, '0', STR_PAD_LEFT)
        ]);

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

    public function updated(Order $order): void
    {
        $this->moveStockForChanges($order);

        // Sync invoice total if order total changes; cancelling or un-cancelling changes what is owed too.
        if ($order->wasChanged(['total_amount', 'status']) && $invoice = $order->invoice) {
            $invoice->updateQuietly(['total_amount' => $order->total_amount]);
            $invoice->recalculateBalance(syncOrderStatus: false); // editing items must not flip a pending order to confirmed
        }
    }

    /**
     * Cancelled orders hold no stock: cancelling puts every line back, un-cancelling takes it out again.
     * Changing the rep or the "from rep stock" switch hands the stock back and takes it again from the new source.
     */
    protected function moveStockForChanges(Order $order): void
    {
        $wasCancelled = $order->getOriginal('status') === 'cancelled';
        $isCancelled = $order->status === 'cancelled';
        $sourceChanged = $order->wasChanged(['sales_rep_id', 'from_sales_rep_stock']);

        if ($wasCancelled && $isCancelled) {
            return;
        }
        if (! $order->wasChanged('status') && ! $sourceChanged) {
            return;
        }
        if ($wasCancelled === $isCancelled && ! $sourceChanged) {
            return;
        }

        foreach ($order->items()->with('product')->get() as $item) {
            if (! $wasCancelled) {
                OrderItemObserver::putBack($order, $item->product, $item->quantity, (int) $item->rep_stock_deducted, $order->getOriginal('sales_rep_id'), $isCancelled ? 'Order cancelled' : 'Order stock source changed');
            }
            if (! $isCancelled) {
                OrderItemObserver::take($item, $order, $wasCancelled ? 'Order un-cancelled' : 'Order stock source changed');
            }
        }
    }
}
