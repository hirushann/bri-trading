<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStats extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.dashboard-stats';

    protected function getColumns(): int
    {
        return 2;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Total Payments Received', 'LKR ' . number_format(Payment::sum('amount'), 2))
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('success'),

            Stat::make('Total Sales', 'LKR ' . number_format(Order::where('status', '!=', 'cancelled')->sum('total_amount'), 2))
                ->description('Total value of non-cancelled orders')
                ->color('success'),

            Stat::make('Total Orders', Order::where('status', '!=', 'cancelled')->count())
                ->description('Total count of non-cancelled orders')
                ->color('primary'),

            Stat::make('Total Profit', 'LKR ' . number_format(
                $totalProfit = \App\Models\OrderItem::whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))
                    ->get()
                    ->sum(fn ($item) => ($item->unit_price - $item->cost_price) * $item->quantity)
            , 2))
                ->description('Net profit from non-cancelled orders')
                ->color('success')
                ->chart([5, 12, 4, 15, 8, 20]),

            Stat::make('Estimated Tax (18% of Profit)', 'LKR ' . number_format($totalProfit * 0.18, 2))
                ->description('VAT payable on profit')
                ->color('warning'),
            
            Stat::make('Orders Today', Order::whereDate('date', today())->count()),
            
            // Stat::make('Low Stock Products', Product::whereColumn('stock_quantity', '<=', 'min_stock_alert')->count())
            //     ->description('Products below minimum stock')
            //     ->descriptionIcon('heroicon-m-arrow-trending-down')
            //     ->color('danger'),
        ];
    }
}
