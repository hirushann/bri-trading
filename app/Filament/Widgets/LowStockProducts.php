<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Models\OrderItem;
use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockProducts extends BaseWidget
{
    use UsesDashboardFilters;

    protected static ?int $sort = 8;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Low Stock — Reorder Soon';

    public function table(Table $table): Table
    {
        return $table
            ->description('Products at or below their minimum stock level, with how many sold in the period.')
            ->query(
                Product::query()
                    ->select('products.*')
                    ->selectSub(
                        OrderItem::whereColumn('product_id', 'products.id')
                            ->whereIn('order_id', $this->orders($this->range())->select('id'))
                            ->selectRaw('COALESCE(SUM(quantity), 0)'),
                        'sold_in_period'
                    )
                    ->whereColumn('stock_quantity', '<=', 'min_stock_alert')
            )
            ->defaultSort('stock_quantity', 'asc')
            ->recordUrl(fn (Product $record) => ProductResource::getUrl('edit', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Product')
                    ->description(fn (Product $record) => $record->sku)
                    ->searchable(),
                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('In stock')
                    ->numeric()
                    ->alignEnd()
                    ->badge()
                    ->color(fn ($state) => $state <= 0 ? 'danger' : 'warning')
                    ->sortable(),
                Tables\Columns\TextColumn::make('min_stock_alert')
                    ->label('Minimum')
                    ->numeric()
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('sold_in_period')
                    ->label('Sold in period')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('All products are above their minimum stock')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
