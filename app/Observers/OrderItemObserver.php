<?php

namespace App\Observers;

use App\Models\OrderItem;
use App\Models\StockMovement;

class OrderItemObserver
{
    /**
     * Handle the OrderItem "creating" event.
     */
    public function creating(OrderItem $orderItem): void
    {
        $product = $orderItem->product;
        if ($product) {
            $orderItem->cost_price = $product->cost_price;
        }
    }

    /**
     * Handle the OrderItem "created" event.
     */
    public function created(OrderItem $orderItem): void
    {
        $product = $orderItem->product;
        if ($product) {
            $product->decrement('stock_quantity', $orderItem->quantity);

            StockMovement::create([
                'product_id' => $product->id,
                'quantity' => $orderItem->quantity,
                'type' => 'out',
                'reference' => 'Order item for order #' . ($orderItem->order->reference ?? 'N/A'),
                'note' => 'Order Item Created',
            ]);
        }
    }

    /**
     * Handle the OrderItem "updated" event.
     */
    public function updated(OrderItem $orderItem): void
    {
        if ($orderItem->isDirty('quantity')) {
            $product = $orderItem->product;
            if ($product) {
                $original = $orderItem->getOriginal('quantity');
                $new = $orderItem->quantity;
                $diff = $new - $original;

                if ($diff > 0) {
                    $product->decrement('stock_quantity', $diff);
                     StockMovement::create([
                        'product_id' => $product->id,
                        'quantity' => $diff,
                        'type' => 'out',
                        'reference' => 'Order item updated #' . ($orderItem->order->reference ?? 'N/A'),
                        'note' => 'Order Item Increased',
                    ]);
                } else {
                    $product->increment('stock_quantity', abs($diff));
                    StockMovement::create([
                        'product_id' => $product->id,
                        'quantity' => abs($diff),
                        'type' => 'in',
                        'reference' => 'Order item updated #' . ($orderItem->order->reference ?? 'N/A'),
                        'note' => 'Order Item Decreased',
                    ]);
                }
            }
        }
    }

    /**
     * Handle the OrderItem "deleted" event.
     */
    public function deleted(OrderItem $orderItem): void
    {
        $product = $orderItem->product;
         if ($product) {
            $product->increment('stock_quantity', $orderItem->quantity);

            StockMovement::create([
                'product_id' => $product->id,
                'quantity' => $orderItem->quantity,
                'type' => 'in',
                'reference' => 'Order item deleted #' . ($orderItem->order->reference ?? 'N/A'),
                'note' => 'Order Item Deleted (Restock)',
            ]);
        }
    }
}
