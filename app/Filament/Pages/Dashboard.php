<?php

namespace App\Filament\Pages;

use App\Filament\Widgets;
use App\Models\Customer;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    use BaseDashboard\Concerns\HasFiltersForm;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'My Dashboard';

    protected static ?string $title = 'My Dashboard';

    protected static string $routePath = '/';

    public function filtersForm(Form $form): Form
    {
        $isAdmin = auth()->user()?->hasRole('Admin');

        return $form->schema([
            Section::make()
                ->schema([
                    Select::make('period')
                        ->options(Widgets\DashboardStats::PERIODS)
                        ->default('this_month')
                        ->selectablePlaceholder(false)
                        ->live(),
                    DatePicker::make('from')
                        ->visible(fn (Get $get) => $get('period') === 'custom')
                        ->maxDate(fn (Get $get) => $get('until') ?: now()),
                    DatePicker::make('until')
                        ->visible(fn (Get $get) => $get('period') === 'custom')
                        ->minDate(fn (Get $get) => $get('from'))
                        ->maxDate(now()),
                    Select::make('sales_rep_id')
                        ->label('Sales rep')
                        ->placeholder('All reps')
                        ->options(fn () => User::whereHas('roles', fn ($q) => $q->where('name', 'Sales Representative'))->pluck('name', 'id'))
                        ->searchable()
                        ->visible($isAdmin),
                    Select::make('customer_id')
                        ->label('Customer')
                        ->placeholder('All customers')
                        ->options(fn () => Customer::orderBy('name')->pluck('name', 'id'))
                        ->searchable(),
                ])
                ->columns(['md' => 3, 'xl' => 5]),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            Widgets\DashboardStats::class,
            Widgets\SalesVsCollectionsChart::class,
            Widgets\ReceivablesAgingChart::class,
            Widgets\TopProducts::class,
            Widgets\TopCustomers::class,
            Widgets\SalesRepPerformance::class,
            Widgets\PendingCheques::class,
            Widgets\LowStockProducts::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return ['md' => 2];
    }
}
