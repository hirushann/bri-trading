<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Models\Invoice;
use Filament\Widgets\ChartWidget;

class ReceivablesAgingChart extends ChartWidget
{
    use UsesDashboardFilters;

    protected static ?int $sort = 3;

    protected static ?string $heading = 'Money Owed by Age';

    protected static ?string $description = 'Unpaid balances on orders in the period, by days since the invoice. Older money is harder to collect.';

    protected static ?string $maxHeight = '280px';

    protected function getData(): array
    {
        // Still owed today on orders placed in the period.
        $invoices = Invoice::where('balance_due', '>', 0)
            ->whereIn('order_id', $this->orders($this->range())->select('id'))
            ->get(['issued_date', 'balance_due']);

        $buckets = ['0–30 days' => 0, '31–60 days' => 0, '61–90 days' => 0, 'Over 90 days' => 0];
        foreach ($invoices as $invoice) {
            $age = $invoice->issued_date ? \Carbon\Carbon::parse($invoice->issued_date)->diffInDays(now()) : 0;
            $bucket = match (true) {
                $age <= 30 => '0–30 days',
                $age <= 60 => '31–60 days',
                $age <= 90 => '61–90 days',
                default => 'Over 90 days',
            };
            $buckets[$bucket] += $invoice->balance_due;
        }

        return [
            'datasets' => [[
                'label' => 'Owed (LKR)',
                'data' => array_values(array_map(fn ($v) => round($v, 2), $buckets)),
                'backgroundColor' => ['#10b981', '#f59e0b', '#f97316', '#ef4444'],
            ]],
            'labels' => array_keys($buckets),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return ['plugins' => ['legend' => ['display' => false]]];
    }
}
