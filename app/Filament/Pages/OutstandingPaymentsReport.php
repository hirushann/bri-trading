<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use App\Models\Invoice;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;

class OutstandingPaymentsReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';
    
    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Outstanding Payments';

    protected static string $view = 'filament.pages.outstanding-payments-report';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Invoice::query()
                    ->where('balance_due', '>', 0)
                    ->latest('issued_date')
            )
            ->columns([
                TextColumn::make('invoice_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.customer.address')
                    ->label('Address / Place')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('issued_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total (LKR)')
                    ->money('LKR')
                    ->sortable(),
                TextColumn::make('paid_amount')
                    ->label('Paid (LKR)')
                    ->money('LKR')
                    ->state(fn (Invoice $record) => $record->total_amount - $record->balance_due),
                TextColumn::make('balance_due')
                    ->label('Balance Due (LKR)')
                    ->money('LKR')
                    ->color('danger')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'unpaid' => 'danger',
                        'partial' => 'warning',
                        'paid' => 'success',
                        default => 'gray',
                    }),
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
                                fn (Builder $query, $date): Builder => $query->whereDate('issued_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issued_date', '<=', $date),
                            );
                    }),
                Filter::make('address')
                    ->form([
                        TextInput::make('place')
                            ->label('Filter by Place/Address')
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['place'],
                            fn (Builder $query, $place): Builder => $query->whereHas(
                                'order.customer',
                                fn (Builder $q) => $q->where('address', 'like', "%{$place}%")
                            )
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
                    return route('reports.outstanding-payments.print', [
                        'from' => $filters['date']['from'] ?? null,
                        'until' => $filters['date']['until'] ?? null,
                        'place' => $filters['address']['place'] ?? null,
                    ]);
                })
                ->openUrlInNewTab(),
        ];
    }
}
