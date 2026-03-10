<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use App\Models\Payment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class IncomeReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    
    protected static ?string $navigationGroup = 'Reports';

    protected static string $view = 'filament.pages.income-report';

    public function table(Table $table): Table
    {
        return $table
            ->query(Payment::query()->latest('transaction_date'))
            ->columns([
                TextColumn::make('transaction_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('invoice.invoice_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice.order.customer.name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cash' => 'success',
                        'cheque' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('amount')
                    ->money('LKR')
                    ->sortable()
                    ->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->label('Total')->money('LKR')),
                TextColumn::make('cheque_number')
                    ->searchable(),
                TextColumn::make('cheque_date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('date')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('transaction_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('transaction_date', '<=', $date),
                            );
                    })
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('print')
                ->label('Print Report')
                ->icon('heroicon-o-printer')
                ->url(function () {
                    $filters = $this->tableFilters ?? [];
                    return route('reports.income.print', [
                        'from' => $filters['date']['from'] ?? null,
                        'until' => $filters['date']['until'] ?? null,
                    ]);
                })
                ->openUrlInNewTab(),
        ];
    }
}
