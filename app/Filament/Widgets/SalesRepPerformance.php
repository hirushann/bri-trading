<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Models\Commission;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SalesRepPerformance extends BaseWidget
{
    use UsesDashboardFilters;

    protected static ?int $sort = 7;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Sales Rep Performance';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasRole('Admin');
    }

    public function table(Table $table): Table
    {
        [$from, $until] = $this->range();
        $dates = [$from->toDateString(), $until->toDateString()];
        $customer = $this->customerId();

        $orders = fn () => Order::whereColumn('orders.sales_rep_id', 'users.id')
            ->where('status', '!=', 'cancelled')
            ->when($customer, fn ($q) => $q->where('customer_id', $customer));

        return $table
            ->description('Sales, collections and returns per rep in the period. Unpaid commission is the current total.')
            ->query(
                User::query()
                    ->select('users.*')
                    ->selectSub($orders()->whereBetween('date', $dates)->selectRaw('COUNT(*)'), 'order_count')
                    ->selectSub($orders()->whereBetween('date', $dates)->selectRaw('COALESCE(SUM(total_amount), 0)'), 'gross_sales')
                    ->selectSub(OrderReturn::whereIn('order_id', $orders()->select('id'))->whereBetween('return_date', $dates)->selectRaw('COALESCE(SUM(amount), 0)'), 'returned')
                    ->selectSub(
                        Payment::whereColumn('payments.sales_rep_id', 'users.id')
                            ->where('status', '!=', 'bounced')
                            ->whereBetween('transaction_date', $dates)
                            ->when($customer, fn ($q) => $q->whereHas('invoice.order', fn ($o) => $o->where('customer_id', $customer)))
                            ->selectRaw('COALESCE(SUM(amount), 0)'),
                        'collected'
                    )
                    ->selectSub(Commission::whereColumn('commissions.user_id', 'users.id')->whereIn('status', ['pending', 'partially_paid'])->selectRaw('COALESCE(SUM(amount - paid_amount), 0)'), 'commission_due')
                    ->whereHas('roles', fn ($q) => $q->where('name', 'Sales Representative'))
                    ->when($this->repId(), fn ($q, $id) => $q->whereKey($id))
            )
            ->defaultSort('gross_sales', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Rep'),
                Tables\Columns\TextColumn::make('order_count')
                    ->label('Orders')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('gross_sales')
                    ->label('Net sales')
                    ->state(fn (User $record) => $record->gross_sales - $record->returned)
                    ->money('LKR')
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('collected')
                    ->money('LKR')
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('returned')
                    ->label('Returns')
                    ->money('LKR')
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'danger' : null)
                    ->sortable(),
                Tables\Columns\TextColumn::make('commission_due')
                    ->label('Unpaid commission')
                    ->money('LKR')
                    ->alignEnd()
                    ->sortable(),
            ])
            ->paginated(false)
            ->emptyStateHeading('No sales reps yet');
    }
}
