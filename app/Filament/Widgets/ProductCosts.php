<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesRepStock;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Cost of the items sold in the period (at the cost saved on each order line) and the cost of
 * what is still in stock (at the product's current cost).
 */
class ProductCosts extends BaseWidget
{
    use UsesDashboardFilters;

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Product Costs';

    public function table(Table $table): Table
    {
        $orderIds = fn () => $this->orders($this->range())->select('id');
        $items = fn () => OrderItem::query()->whereColumn('product_id', 'products.id')->whereIn('order_id', $orderIds());
        $returned = fn (string $expr) => DB::table('order_return_items')
            ->join('order_items', 'order_items.id', '=', 'order_return_items.order_item_id')
            ->whereColumn('order_items.product_id', 'products.id')
            ->whereIn('order_items.order_id', $orderIds())
            ->selectRaw("COALESCE(SUM({$expr}), 0)");

        // With a rep chosen (or a rep looking), "remaining" means that rep's stock; otherwise main stock plus all reps.
        $repId = $this->repId();
        $repStock = SalesRepStock::query()
            ->whereColumn('product_id', 'products.id')
            ->when($repId, fn ($q) => $q->where('user_id', $repId))
            ->selectRaw('COALESCE(SUM(quantity), 0)');

        [$soldCost, $stockCost] = $this->totals($orderIds, $repId);

        return $table
            ->description('Sold cost: ' . $this->money($soldCost) . ' in the period (after returns)  ·  Remaining stock at cost: ' . $this->money($stockCost) . ($repId ? ' (this rep\'s stock)' : ' (main + rep stock)'))
            ->query(
                Product::query()
                    ->select('products.*')
                    ->selectSub($items()->selectRaw('COALESCE(SUM(quantity), 0)'), 'sold_qty')
                    ->selectSub($returned('order_return_items.quantity'), 'returned_qty')
                    ->selectSub($items()->selectRaw('COALESCE(SUM(cost_price * quantity), 0)'), 'sold_cost')
                    ->selectSub($returned('order_return_items.quantity * order_items.cost_price'), 'returned_cost')
                    ->selectSub($repStock, 'rep_qty')
            )
            ->defaultSort('sold_cost', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Product')
                    ->description(fn (Product $record) => $record->sku)
                    ->searchable(['name', 'sku']),
                Tables\Columns\TextColumn::make('cost_price')
                    ->label('Unit cost')
                    ->money('LKR')
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sold_qty')
                    ->label('Sold')
                    ->state(fn (Product $record) => $record->sold_qty - $record->returned_qty)
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sold_cost')
                    ->label('Cost of sold')
                    ->state(fn (Product $record) => $record->sold_cost - $record->returned_cost)
                    ->money('LKR')
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('remaining_qty')
                    ->label('Remaining')
                    ->state(fn (Product $record) => $this->remainingQty($record, $repId))
                    ->description(fn (Product $record) => $repId ? null : "{$record->stock_quantity} main · {$record->rep_qty} reps")
                    ->numeric()
                    ->alignEnd()
                    ->color(fn ($state) => $state < 0 ? 'danger' : null),
                Tables\Columns\TextColumn::make('remaining_cost')
                    ->label('Remaining cost')
                    // Negative stock (oversold) has no value on the shelf; matches the total above.
                    ->state(fn (Product $record) => max(0, $this->remainingQty($record, $repId)) * $record->cost_price)
                    ->money('LKR')
                    ->alignEnd(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('show')
                    ->options([
                        'sold' => 'Sold in period',
                        'remaining' => 'Still in stock',
                        'sold_out' => 'Out of stock',
                    ])
                    ->placeholder('All products')
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'sold' => $query->whereIn('products.id', OrderItem::whereIn('order_id', $orderIds())->select('product_id')),
                        'remaining' => $repId ? $query->where($repStock, '>', 0) : $query->where(fn ($q) => $q->where('stock_quantity', '>', 0)->orWhere($repStock, '>', 0)),
                        'sold_out' => $repId ? $query->where($repStock, '<=', 0) : $query->where('stock_quantity', '<=', 0)->where($repStock, '<=', 0),
                        default => $query,
                    }),
            ])
            ->paginated([5, 10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('No products match');
    }

    protected function remainingQty(Product $record, ?int $repId): int
    {
        return (int) $record->rep_qty + ($repId ? 0 : (int) $record->stock_quantity);
    }

    /** @return array{0: float, 1: float} cost of sold items after returns, cost of remaining stock */
    protected function totals(\Closure $orderIds, ?int $repId): array
    {
        $sold = (float) OrderItem::whereIn('order_id', $orderIds())->sum(DB::raw('cost_price * quantity'))
            - (float) DB::table('order_return_items')
                ->join('order_items', 'order_items.id', '=', 'order_return_items.order_item_id')
                ->whereIn('order_items.order_id', $orderIds())
                ->sum(DB::raw('order_return_items.quantity * order_items.cost_price'));

        $repStock = (float) SalesRepStock::join('products', 'products.id', '=', 'sales_rep_stocks.product_id')
            ->when($repId, fn ($q) => $q->where('sales_rep_stocks.user_id', $repId))
            ->sum(DB::raw('sales_rep_stocks.quantity * products.cost_price'));
        $mainStock = $repId ? 0 : (float) Product::where('stock_quantity', '>', 0)->sum(DB::raw('stock_quantity * cost_price'));

        return [$sold, $mainStock + $repStock];
    }
}
