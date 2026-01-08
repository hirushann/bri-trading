<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesCommissionWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        $pendingCommission = \App\Models\Commission::where('user_id', $user->id)
            ->where('status', 'pending')
            ->sum('amount');

        $paidCommission = \App\Models\Commission::where('user_id', $user->id)
            ->where('status', 'paid')
            ->sum('amount');

        return [
            Stat::make('Pending Commission', number_format($pendingCommission, 2) . ' LKR')
                ->description('Total unpaid commission')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),
            Stat::make('Paid Commission', number_format($paidCommission, 2) . ' LKR')
                ->description('Total paid commission')
                ->color('success'),
        ];
    }
}
