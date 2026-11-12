<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Models\OrderItem;
use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class TopProducts extends BaseWidget
{
    use UsesDashboardFilters;

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Top Products';

    public function table(Table $table): Table
    {
        $items = fn () => OrderItem::query()->whereColumn('product_id', 'products.id')->whereIn('order_id', $this->orders($this->range())->select('id'));
        $returned = fn (string $expr) => DB::table('order_return_items')
            ->join('order_items', 'order_items.id', '=', 'order_return_items.order_item_id')
            ->whereColumn('order_items.product_id', 'products.id')
            ->whereIn('order_items.order_id', $this->orders($this->range())->select('id'))
            ->selectRaw("COALESCE(SUM({$expr}), 0)");

        return $table
            ->description('Best sellers in the selected period, after order discounts and returns.')
            ->query(
                Product::query()
                    ->select('products.*')
                    ->selectSub($items()->selectRaw('COALESCE(SUM(quantity), 0)'), 'sold_qty')
                    ->selectSub($returned('order_return_items.quantity'), 'returned_qty')
                    ->selectSub($items()->selectRaw('COALESCE(SUM(unit_price * quantity * ' . self::PAID_RATIO_SQL . '), 0)'), 'revenue')
                    ->selectSub($returned('order_return_items.amount'), 'returned_revenue')
                    ->selectSub($items()->selectRaw('COALESCE(SUM((unit_price * ' . self::PAID_RATIO_SQL . ' - cost_price) * quantity), 0)'), 'profit')
                    ->selectSub($returned('order_return_items.amount - order_return_items.quantity * order_items.cost_price'), 'returned_profit')
                    ->whereIn('id', OrderItem::whereIn('order_id', $this->orders($this->range())->select('id'))->select('product_id'))
            )
            ->defaultSort('revenue', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Product')
                    ->description(fn (Product $record) => $record->sku)
                    ->searchable(),
                Tables\Columns\TextColumn::make('sold_qty')
                    ->label('Units')
                    ->state(fn (Product $record) => $record->sold_qty - $record->returned_qty)
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('revenue')
                    ->state(fn (Product $record) => $record->revenue - $record->returned_revenue)
                    ->money('LKR')
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('profit')
                    ->state(fn (Product $record) => $record->profit - $record->returned_profit)
                    ->money('LKR')
                    ->alignEnd()
                    ->color(fn ($state) => $state < 0 ? 'danger' : null)
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('In stock')
                    ->numeric()
                    ->alignEnd()
                    ->color(fn (Product $record) => $record->stock_quantity <= $record->min_stock_alert ? 'danger' : null),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('No sales in this period');
    }
}
