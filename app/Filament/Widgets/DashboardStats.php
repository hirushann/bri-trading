<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Models\Invoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStats extends BaseWidget
{
    use UsesDashboardFilters;

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $range = $this->range();
        $prev = $this->previousRange();
        $isAdmin = auth()->user()?->hasRole('Admin');

        $sales = $this->netSales($range);
        $collected = (float) $this->payments($range)->sum('amount');
        $profit = $this->grossProfit($range);
        $expenses = $this->expenses($range);
        $returns = (float) $this->returns($range)->sum('amount');
        $orderCount = $this->orders($range)->count();

        // Still owed today on orders placed in the period.
        $outstanding = (float) Invoice::whereIn('order_id', $this->orders($range)->select('id'))->sum('balance_due');
        $overdue = (float) Invoice::whereIn('order_id', $this->orders($range)->select('id'))
            ->where('balance_due', '>', 0)
            ->whereDate('issued_date', '<', now()->subDays(30))
            ->sum('balance_due');

        $stats = [
            $this->stat('Net Sales', $sales, $prev ? $this->netSales($prev) : null)
                ->icon('heroicon-o-shopping-bag'),
            $this->stat('Collected', $collected, $prev ? (float) $this->payments($prev)->sum('amount') : null)
                ->icon('heroicon-o-banknotes'),
            Stat::make('Outstanding', $this->money($outstanding))
                ->description($overdue > 0 ? $this->money($overdue) . ' over 30 days old' : 'Nothing over 30 days old')
                ->descriptionIcon($overdue > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($overdue > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-clock'),
            $this->stat('Orders', $orderCount, $prev ? $this->orders($prev)->count() : null, money: false)
                ->icon('heroicon-o-shopping-cart'),
            $this->stat('Gross Profit', $profit, $prev ? $this->grossProfit($prev) : null)
                ->icon('heroicon-o-arrow-trending-up'),
        ];

        // Company costs only make sense for an admin looking at the whole business.
        if ($isAdmin && ! $this->repId() && ! $this->customerId()) {
            $stats[] = $this->stat('Expenses', $expenses, $prev ? $this->expenses($prev) : null, higherIsBetter: false)
                ->icon('heroicon-o-receipt-percent');
            $stats[] = $this->stat('Net Profit', $profit - $expenses, $prev ? $this->grossProfit($prev) - $this->expenses($prev) : null)
                ->icon('heroicon-o-scale');
        }

        $stats[] = $this->stat('Returns', $returns, $prev ? (float) $this->returns($prev)->sum('amount') : null, higherIsBetter: false)
            ->icon('heroicon-o-arrow-uturn-left');

        return $stats;
    }

    protected function stat(string $label, float $value, ?float $previous, bool $money = true, bool $higherIsBetter = true): Stat
    {
        $stat = Stat::make($label, $money ? $this->money($value) : number_format($value));
        $change = $this->change($value, $previous);

        if (! $change) {
            return $stat->description($previous === null ? null : 'No data for previous period')->color('gray');
        }

        return $stat
            ->description($change['text'])
            ->descriptionIcon($change['up'] ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
            ->color($change['up'] === $higherIsBetter ? 'success' : 'danger');
    }
}
