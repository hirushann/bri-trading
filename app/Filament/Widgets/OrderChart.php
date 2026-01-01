<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class OrderChart extends ChartWidget
{
    protected static ?string $heading = 'Orders per Day';

    protected function getData(): array
    {
        $data = \App\Models\Order::selectRaw('DATE(date) as day, COUNT(*) as count')
            ->where('date', '>=', now()->subDays(7))
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count', 'day')
            ->toArray();

        // Fill missing days with 0
        $chartData = [];
        $labels = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = $date;
            $chartData[] = $data[$date] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $chartData,
                    'fill' => 'start',
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
