<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;

class SalesVsCollectionsChart extends ChartWidget
{
    use UsesDashboardFilters;

    protected static ?int $sort = 2;

    protected static ?string $heading = 'Sales vs Collections';

    protected static ?string $description = 'Are we collecting what we sell?';

    protected static ?string $maxHeight = '280px';

    protected function getData(): array
    {
        [$from, $until] = $this->range();

        // Long ranges by month, short ranges by day.
        $byMonth = $from->diffInDays($until) > 62;
        if (($this->filters['period'] ?? null) === 'all') {
            $first = $this->orders()->min('date');
            $from = $first ? \Carbon\CarbonImmutable::parse($first)->startOfMonth() : $from;
        }

        $key = fn ($date) => \Carbon\Carbon::parse($date)->format($byMonth ? 'Y-m' : 'Y-m-d');

        $sales = $this->orders([$from, $until])->get(['date', 'total_amount'])
            ->groupBy(fn ($o) => $key($o->date))->map->sum('total_amount');
        $returns = $this->returns([$from, $until])->get(['return_date', 'amount'])
            ->groupBy(fn ($r) => $key($r->return_date))->map->sum('amount');
        $collected = $this->payments([$from, $until])->get(['transaction_date', 'amount'])
            ->groupBy(fn ($p) => $key($p->transaction_date))->map->sum('amount');

        $labels = $salesData = $collectedData = [];
        foreach (CarbonPeriod::create($from, $byMonth ? '1 month' : '1 day', $until) as $date) {
            $k = $date->format($byMonth ? 'Y-m' : 'Y-m-d');
            $labels[] = $date->format($byMonth ? 'M Y' : 'M j');
            $salesData[] = round(($sales[$k] ?? 0) - ($returns[$k] ?? 0), 2);
            $collectedData[] = round($collected[$k] ?? 0, 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Net sales',
                    'data' => $salesData,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.7)',
                    'borderColor' => '#f59e0b',
                ],
                [
                    'label' => 'Collected',
                    'data' => $collectedData,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.7)',
                    'borderColor' => '#10b981',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
