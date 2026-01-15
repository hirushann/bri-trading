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
        $order = $orderItem->order;

        if ($product && $order) {
            $source = 'main';
            $repDeducted = 0;

            if ($order->from_sales_rep_stock && $order->sales_rep_id) {
                // Check Sales Rep Stock
                $stock = \App\Models\SalesRepStock::firstOrCreate(
                    ['user_id' => $order->sales_rep_id, 'product_id' => $product->id],
                    ['quantity' => 0]
                );
                
                $repAvailable = $stock->quantity;
                $required = $orderItem->quantity;

                // Take as much as possible from Rep
                $takeFromRep = min($repAvailable, $required);
                $takeFromMain = $required - $takeFromRep;

                if ($takeFromRep > 0) {
                    $stock->decrement('quantity', $takeFromRep);
                     StockMovement::create([
                        'product_id' => $product->id,
                        'quantity' => $takeFromRep,
                        'type' => 'out',
                        'reference' => 'Order #' . $order->reference . ' (Rep: ' . $order->salesRep->name . ')',
                        'note' => 'Order Item Created (Rep Stock)',
                    ]);
                }

                if ($takeFromMain > 0) {
                    $product->decrement('stock_quantity', $takeFromMain);
                    StockMovement::create([
                        'product_id' => $product->id,
                        'quantity' => $takeFromMain,
                        'type' => 'out',
                        'reference' => 'Order item for order #' . ($order->reference ?? 'N/A'),
                        'note' => 'Order Item Created (Main Stock)',
                    ]);
                }

                $repDeducted = $takeFromRep;
                
                // Determine source label
                if ($takeFromMain == 0) {
                    $source = 'rep';
                } elseif ($takeFromRep == 0) {
                    $source = 'main';
                } else {
                    $source = 'split'; // Or keep 'rep' to indicate intent? Let's use 'split' for clarity if needed, or stick to 'rep' as primary intent.
                    // Actually, 'stock_deducted_from' is less critical now that we have 'rep_stock_deducted'.
                    // Let's keep 'stock_deducted_from' as the "primary" or "requested" source, or 'split'.
                    $source = 'split';
                }
                
            } else {
                // Default: Deduct from Main Stock
                $product->decrement('stock_quantity', $orderItem->quantity);
                $source = 'main';

                StockMovement::create([
                    'product_id' => $product->id,
                    'quantity' => $orderItem->quantity,
                    'type' => 'out',
                    'reference' => 'Order item for order #' . ($order->reference ?? 'N/A'),
                    'note' => 'Order Item Created',
                ]);
            }

            // Save the source and detailed deduction info
            $orderItem->updateQuietly([
                'stock_deducted_from' => $source,
                'rep_stock_deducted' => $repDeducted
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
                $originalQty = $orderItem->getOriginal('quantity');
                $newQty = $orderItem->quantity;
                $diff = $newQty - $originalQty;
                
                $originalRepDeducted = $orderItem->rep_stock_deducted ?? 0;
                $originalMainDeducted = $originalQty - $originalRepDeducted;

                // Rational Approach:
                // 1. Temporarily restore EVERYTHING to calculate true availability
                if ($originalRepDeducted > 0 && $orderItem->order->sales_rep_id) {
                     $stock = \App\Models\SalesRepStock::firstOrCreate(
                        ['user_id' => $orderItem->order->sales_rep_id, 'product_id' => $product->id],
                        ['quantity' => 0]
                    );
                    $stock->increment('quantity', $originalRepDeducted);
                }
                if ($originalMainDeducted > 0) {
                    $product->increment('stock_quantity', $originalMainDeducted);
                }

                // 2. Perform fresh deduction based on new Quantity and (now restored) Availability
                 $repDeducted = 0;
                 $source = 'main';
                 
                 if ($orderItem->order->from_sales_rep_stock && $orderItem->order->sales_rep_id) {
                    $stock = \App\Models\SalesRepStock::firstOrCreate(
                        ['user_id' => $orderItem->order->sales_rep_id, 'product_id' => $product->id],
                        ['quantity' => 0]
                    );
                    $repAvailable = $stock->quantity;
                    
                    $takeFromRep = min($repAvailable, $newQty);
                    $takeFromMain = $newQty - $takeFromRep;

                    if ($takeFromRep > 0) {
                        $stock->decrement('quantity', $takeFromRep);
                        // Log movement? We are "netting" manually here, but for logs maybe we should log the DIFF?
                        // Logging full restore + full deduct is noisy.
                        // Let's log the net change.
                        
                        $netRepChange = $takeFromRep - $originalRepDeducted;
                        if ($netRepChange > 0) {
                             StockMovement::create([
                                'product_id' => $product->id,
                                'quantity' => $netRepChange,
                                'type' => 'out',
                                'reference' => 'Order item updated #' . ($orderItem->order->reference ?? 'N/A'),
                                'note' => 'Order Item Increased (Rep)',
                            ]);
                        } elseif ($netRepChange < 0) {
                             StockMovement::create([
                                'product_id' => $product->id,
                                'quantity' => abs($netRepChange),
                                'type' => 'in',
                                'reference' => 'Order item updated #' . ($orderItem->order->reference ?? 'N/A'),
                                'note' => 'Order Item Decreased (Rep)',
                            ]);
                        }
                    } else {
                         // Only restore rep if we previously took it and now take 0? handled by netChange
                         if ($originalRepDeducted > 0) {
                             // We conceptually put it back in step 1, but we need to verify if we decreased it effectively
                             // Yes, netRepChange covers it.
                             $netRepChange = 0 - $originalRepDeducted;
                             StockMovement::create([
                                'product_id' => $product->id,
                                'quantity' => abs($netRepChange),
                                'type' => 'in',
                                'reference' => 'Order item updated #' . ($orderItem->order->reference ?? 'N/A'),
                                'note' => 'Order Item Decreased (Rep)',
                            ]);
                         }
                    }

                    if ($takeFromMain > 0) {
                        $product->decrement('stock_quantity', $takeFromMain);
                        
                        $netMainChange = $takeFromMain - $originalMainDeducted;
                        if ($netMainChange > 0) {
                            StockMovement::create([
                                'product_id' => $product->id,
                                'quantity' => $netMainChange,
                                'type' => 'out',
                                'reference' => 'Order item updated #' . ($orderItem->order->reference ?? 'N/A'),
                                'note' => 'Order Item Increased (Main)',
                            ]);
                        } elseif ($netMainChange < 0) {
                             StockMovement::create([
                                'product_id' => $product->id,
                                'quantity' => abs($netMainChange),
                                'type' => 'in',
                                'reference' => 'Order item updated #' . ($orderItem->order->reference ?? 'N/A'),
                                'note' => 'Order Item Decreased (Main)',
                            ]);
                        }
                    } else {
                         if ($originalMainDeducted > 0) {
                             $netMainChange = 0 - $originalMainDeducted;
                             StockMovement::create([
                                'product_id' => $product->id,
                                'quantity' => abs($netMainChange),
                                'type' => 'in',
                                'reference' => 'Order item updated #' . ($orderItem->order->reference ?? 'N/A'),
                                'note' => 'Order Item Decreased (Main)',
                            ]);
                         }
                    }

                    $repDeducted = $takeFromRep;
                     if ($takeFromMain == 0) $source = 'rep';
                     elseif ($takeFromRep == 0) $source = 'main';
                     else $source = 'split';

                 } else {
                     // Not from Rep Stock (Standard)
                     $product->decrement('stock_quantity', $newQty);
                     $source = 'main';
                     
                     $netMainChange = $newQty - $originalMainDeducted;
                     // Log net change... similar logic
                     if ($netMainChange > 0) {
                         StockMovement::create([
                            'product_id' => $product->id,
                            'quantity' => $netMainChange,
                            'type' => 'out',
                            'reference' => 'Order item updated',
                            'note' => 'Order Item Increased',
                        ]);
                     } elseif ($netMainChange < 0) {
                          StockMovement::create([
                            'product_id' => $product->id,
                            'quantity' => abs($netMainChange),
                            'type' => 'in',
                             'reference' => 'Order item updated',
                            'note' => 'Order Item Decreased',
                        ]);
                     }
                 }

                 $orderItem->updateQuietly([
                    'stock_deducted_from' => $source,
                    'rep_stock_deducted' => $repDeducted
                ]);
            }
        }
    }

    /**
     * Handle the OrderItem "deleted" event.
     */
    public function deleted(OrderItem $orderItem): void
    {
        $product = $orderItem->product;
        $order = $orderItem->order;

        if ($product && $order) {
            $repDeducted = $orderItem->rep_stock_deducted ?? 0;
            $mainDeducted = $orderItem->quantity - $repDeducted;

            // Restore Rep Stock
            if ($repDeducted > 0 && $order->sales_rep_id) {
                $stock = \App\Models\SalesRepStock::firstOrCreate(
                    ['user_id' => $order->sales_rep_id, 'product_id' => $product->id],
                    ['quantity' => 0]
                );
                $stock->increment('quantity', $repDeducted);

                StockMovement::create([
                    'product_id' => $product->id,
                    'quantity' => $repDeducted,
                    'type' => 'in',
                    'reference' => 'Order item deleted #' . ($order->reference ?? 'N/A'),
                    'note' => 'Order Item Deleted (Rep Restock)',
                ]);
            }

            // Restore Main Stock
            if ($mainDeducted > 0) {
                $product->increment('stock_quantity', $mainDeducted);

                StockMovement::create([
                    'product_id' => $product->id,
                    'quantity' => $mainDeducted,
                    'type' => 'in',
                    'reference' => 'Order item deleted #' . ($order->reference ?? 'N/A'),
                    'note' => 'Order Item Deleted (Main Restock)',
                ]);
            }
        }
    }
}
