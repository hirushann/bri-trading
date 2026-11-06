<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CustomerResource;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\OrderReturn;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopCustomers extends BaseWidget
{
    use UsesDashboardFilters;

    protected static ?int $sort = 5;

    protected static ?string $heading = 'Top Customers';

    public function table(Table $table): Table
    {
        $range = $this->range();
        $orderIds = fn ($range = null) => $this->orders($range)->whereColumn('orders.customer_id', 'customers.id')->select('id');

        return $table
            ->description('Who buys the most in the period, and how much they still owe overall.')
            ->query(
                Customer::query()
                    ->select('customers.*')
                    ->selectSub($this->orders($range)->whereColumn('orders.customer_id', 'customers.id')->selectRaw('COUNT(*)'), 'order_count')
                    ->selectSub($this->orders($range)->whereColumn('orders.customer_id', 'customers.id')->selectRaw('COALESCE(SUM(total_amount), 0)'), 'gross_sales')
                    ->selectSub(OrderReturn::whereIn('order_id', $orderIds($range))->selectRaw('COALESCE(SUM(amount), 0)'), 'returned')
                    ->selectSub(Invoice::whereIn('order_id', $orderIds())->selectRaw('COALESCE(SUM(balance_due), 0)'), 'owed')
                    ->whereIn('id', $this->orders($range)->select('customer_id'))
            )
            ->defaultSort('gross_sales', 'desc')
            ->recordUrl(fn (Customer $record) => CustomerResource::getUrl('edit', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Customer')
                    ->description(fn (Customer $record) => $record->phone)
                    ->searchable(),
                Tables\Columns\TextColumn::make('order_count')
                    ->label('Orders')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('gross_sales')
                    ->label('Net sales')
                    ->state(fn (Customer $record) => $record->gross_sales - $record->returned)
                    ->money('LKR')
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('owed')
                    ->label('Owes now')
                    ->money('LKR')
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->sortable(),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('No customer orders in this period');
    }
}
