<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesRepStock;
use App\Models\StockMovement;

/**
 * Keeps stock in step with order lines. A cancelled order holds no stock, so its lines move nothing.
 */
class OrderItemObserver
{
    public function saving(OrderItem $orderItem): void
    {
        $orderItem->subtotal = round($orderItem->unit_price * $orderItem->quantity, 2);
    }

    public function saved(OrderItem $orderItem): void
    {
        $orderItem->order()->first()?->syncTotal();
    }

    public function deleted(OrderItem $orderItem): void
    {
        $orderItem->order()->first()?->syncTotal();
    }

    public function creating(OrderItem $orderItem): void
    {
        $orderItem->cost_price = $orderItem->product?->cost_price ?? $orderItem->cost_price;
    }

    public function updating(OrderItem $orderItem): void
    {
        // A line switched to another product costs what that product costs.
        if ($orderItem->isDirty('product_id')) {
            $orderItem->cost_price = $orderItem->product()->first()?->cost_price ?? $orderItem->cost_price;
        }
    }

    public function created(OrderItem $orderItem): void
    {
        $order = $orderItem->order()->first();
        if ($order && $order->status !== 'cancelled') {
            self::take($orderItem, $order, 'Order item created');
        }
    }

    public function updated(OrderItem $orderItem): void
    {
        $order = $orderItem->order()->first();
        if (! $order || $order->status === 'cancelled' || ! $orderItem->wasChanged(['quantity', 'product_id'])) {
            return;
        }

        // Put back exactly what the line held before, then take what it holds now.
        self::putBack(
            $order,
            Product::find($orderItem->getOriginal('product_id')),
            (int) $orderItem->getOriginal('quantity'),
            // Fresh from the database: saving the order in the same form may have just moved this line's stock.
            (int) OrderItem::whereKey($orderItem->id)->value('rep_stock_deducted'),
            $order->sales_rep_id,
            'Order item changed'
        );
        self::take($orderItem, $order, 'Order item changed');
    }

    // Before the row goes, so the saved values can be read (saving the order in the same form may have moved them).
    public function deleting(OrderItem $orderItem): void
    {
        $order = $orderItem->order()->first();
        $saved = OrderItem::find($orderItem->id);
        if ($order && $saved && $order->status !== 'cancelled') {
            self::putBack($order, $saved->product, $saved->quantity, (int) $saved->rep_stock_deducted, $order->sales_rep_id, 'Order item deleted');
        }
    }

    /**
     * Take the line's quantity out of stock: from the rep first when the order says so, the rest from main stock.
     */
    public static function take(OrderItem $item, Order $order, string $note): void
    {
        $product = $item->product()->first();
        if (! $product) {
            return;
        }

        $fromRep = 0;
        if ($order->from_sales_rep_stock && $order->sales_rep_id) {
            $stock = SalesRepStock::firstOrCreate(['user_id' => $order->sales_rep_id, 'product_id' => $product->id], ['quantity' => 0]);
            $fromRep = max(0, min($stock->quantity, $item->quantity));
            if ($fromRep > 0) {
                $stock->decrement('quantity', $fromRep);
                self::log($product, $fromRep, 'out', $order, $note . ' (Rep Stock)');
            }
        }

        $fromMain = $item->quantity - $fromRep;
        if ($fromMain > 0) {
            $product->decrement('stock_quantity', $fromMain);
            self::log($product, $fromMain, 'out', $order, $note . ' (Main Stock)');
        }

        $item->updateQuietly([
            'stock_deducted_from' => $fromMain == 0 ? 'rep' : ($fromRep == 0 ? 'main' : 'split'),
            'rep_stock_deducted' => $fromRep,
        ]);
    }

    /**
     * Put back stock a line took: $fromRep units to that rep, the rest to main stock.
     */
    public static function putBack(Order $order, ?Product $product, int $quantity, int $fromRep, ?int $repId, string $note): void
    {
        if (! $product || $quantity <= 0) {
            return;
        }

        $fromRep = $repId ? min($fromRep, $quantity) : 0;
        if ($fromRep > 0) {
            SalesRepStock::firstOrCreate(['user_id' => $repId, 'product_id' => $product->id], ['quantity' => 0])->increment('quantity', $fromRep);
            self::log($product, $fromRep, 'in', $order, $note . ' (Rep Stock)');
        }

        $toMain = $quantity - $fromRep;
        if ($toMain > 0) {
            $product->increment('stock_quantity', $toMain);
            self::log($product, $toMain, 'in', $order, $note . ' (Main Stock)');
        }
    }

    protected static function log(Product $product, int $quantity, string $type, Order $order, string $note): void
    {
        StockMovement::create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'type' => $type,
            'reference' => 'Order #' . ($order->reference ?? 'N/A'),
            'note' => $note,
        ]);
    }
}
