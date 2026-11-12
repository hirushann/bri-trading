<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderReturn extends Model
{
    protected $guarded = [];

    protected $casts = [
        'return_date' => 'date',
    ];

    protected static function booted(): void
    {
        // Undoing a return takes the stock back out and puts the amount back on the invoice.
        static::deleting(function (OrderReturn $return) {
            foreach ($return->items()->with('orderItem.product')->get() as $item) {
                $return->moveStock($item->orderItem->product, $item->quantity, 'out', 'Return undone');
            }
        });

        static::deleted(function (OrderReturn $return) {
            $order = $return->order()->first(); // fresh: the loaded relation can predate the order becoming "returned"
            if (! $order) {
                return;
            }
            if ($order->status === 'returned') {
                $order->updateQuietly(['status' => 'confirmed']);
            }
            $order->invoice?->recalculateBalance();
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }

    /**
     * Record a return. $quantities is [order_item_id => quantity to return]; zero rows are ignored.
     * Stock goes back to main stock or the order's sales rep, and the value is credited against the invoice.
     * The value follows the order discount, so returning everything credits exactly the order total.
     */
    public static function record(Order $order, array $quantities, array $data): self
    {
        return DB::transaction(function () use ($order, $quantities, $data) {
            $order->load('items.product');

            if ($order->status === 'cancelled') {
                throw new InvalidArgumentException('A cancelled order has already put its stock back, so it cannot have returns.');
            }
            $quantities = array_filter(array_map('intval', $quantities));

            if ($quantities === []) {
                throw new InvalidArgumentException('Choose at least one item to return.');
            }

            $itemsTotal = $order->items->sum(fn ($i) => $i->unit_price * $i->quantity);
            $ratio = $itemsTotal > 0 ? $order->total_amount / $itemsTotal : 0; // spreads the order discount

            $return = $order->returns()->create([
                'return_date' => $data['return_date'] ?? now(),
                'restock_to' => ($data['restock_to'] ?? 'main') === 'rep' && $order->sales_rep_id ? 'rep' : 'main',
                'reason' => $data['reason'] ?? null,
            ]);

            $total = 0;
            foreach ($order->items as $orderItem) {
                $qty = $quantities[$orderItem->id] ?? 0;
                if ($qty <= 0) {
                    continue;
                }

                $left = $orderItem->quantity - $orderItem->returnedQuantity();
                if ($qty > $left) {
                    throw new InvalidArgumentException("Only {$left} of {$orderItem->product?->name} can still be returned.");
                }

                $amount = round($orderItem->unit_price * $qty * $ratio, 2);
                $return->items()->create(['order_item_id' => $orderItem->id, 'quantity' => $qty, 'amount' => $amount]);
                $return->moveStock($orderItem->product, $qty, 'in', 'Returned');
                $total += $amount;
            }

            $return->update(['amount' => $total]);

            if ($order->isFullyReturned()) {
                $order->update(['status' => 'returned']);
            }
            $order->invoice?->recalculateBalance();

            return $return;
        });
    }

    /**
     * $direction 'in' puts stock back (the return), 'out' takes it away again (undoing the return).
     */
    protected function moveStock(?Product $product, int $qty, string $direction, string $note): void
    {
        if (! $product || $qty <= 0) {
            return;
        }

        $order = $this->order;
        $toRep = $this->restock_to === 'rep' && $order->sales_rep_id;
        $change = $direction === 'in' ? $qty : -$qty;

        if ($toRep) {
            SalesRepStock::firstOrCreate(
                ['user_id' => $order->sales_rep_id, 'product_id' => $product->id],
                ['quantity' => 0]
            )->increment('quantity', $change);
        } else {
            $product->increment('stock_quantity', $change);
        }

        StockMovement::create([
            'product_id' => $product->id,
            'quantity' => $qty,
            'type' => $direction,
            'reference' => 'Return for order #' . $order->reference . ($toRep ? ' (Rep: ' . $order->salesRep?->name . ')' : ''),
            'note' => $note . ($toRep ? ' (Rep Stock)' : ' (Main Stock)'),
        ]);
    }
}
