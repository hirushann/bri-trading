<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class VatReport extends Page implements \Filament\Tables\Contracts\HasTable
{
    use \Filament\Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    
    protected static ?string $navigationGroup = 'Reports';

    protected static string $view = 'filament.pages.vat-report';

    public function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table
            ->query(\App\Models\Order::query()->where('status', '!=', 'cancelled')->withSum('returns', 'amount')->latest('date'))
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('reference')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total (Inc. VAT)')
                    ->description(fn (\App\Models\Order $record) => $record->returns_sum_amount > 0 ? 'After LKR ' . number_format($record->returns_sum_amount, 2) . ' returned' : null)
                    ->state(fn (\App\Models\Order $record) => self::sold($record))
                    ->money('LKR')
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('net_amount')
                    ->label('Net Amount')
                    ->money('LKR')
                    ->state(fn (\App\Models\Order $record) => self::sold($record) / 1.18),
                \Filament\Tables\Columns\TextColumn::make('vat_amount')
                    ->label('VAT (18%)')
                    ->money('LKR')
                    ->state(fn (\App\Models\Order $record) => self::sold($record) - self::sold($record) / 1.18)
                    ->color('danger'),
            ])
            ->filters([
                \Filament\Tables\Filters\Filter::make('date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from'),
                        \Filament\Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('date', '<=', $date),
                            );
                    })
            ]);
    }

    /** What the customer was finally charged: order total less returns. */
    protected static function sold(\App\Models\Order $record): float
    {
        return (float) $record->total_amount - (float) $record->returns_sum_amount;
    }
}
