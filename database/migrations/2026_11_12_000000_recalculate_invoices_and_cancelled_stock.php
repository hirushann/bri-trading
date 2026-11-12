<?php

use App\Models\Invoice;
use App\Models\Order;
use App\Observers\OrderItemObserver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data only, no table changes. Brings existing records in line with the calculation fixes:
 * - orders cancelled before the fix never put their stock back, so put it back now (cancelled orders hold no stock);
 * - recalculate every invoice (cancelled orders owe nothing) and the unpaid commission that follows from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            Order::where('status', 'cancelled')->whereDoesntHave('returns')->with('items.product')->each(function (Order $order) {
                foreach ($order->items as $item) {
                    OrderItemObserver::putBack($order, $item->product, $item->quantity, (int) $item->rep_stock_deducted, $order->sales_rep_id, 'Order cancelled (stock put back by system fix)');
                }
            });

            Invoice::query()->each(fn (Invoice $invoice) => $invoice->recalculateBalance(syncOrderStatus: false));
        });
    }

    public function down(): void
    {
        // Stock and balances moved on from here; nothing safe to undo automatically.
    }
};
