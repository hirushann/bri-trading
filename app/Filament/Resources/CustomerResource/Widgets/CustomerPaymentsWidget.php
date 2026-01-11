<?php

namespace App\Filament\Resources\CustomerResource\Widgets;

use App\Models\Payment;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;

class CustomerPaymentsWidget extends BaseWidget
{
    public ?Model $record = null;

    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $heading = 'Payment History';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Payment::query()
                    ->whereHas('invoice.order', fn ($query) => $query->where('customer_id', $this->record->id))
                    ->orderBy('transaction_date', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('LKR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('method')
                    ->badge(),
                Tables\Columns\TextColumn::make('cheque_number')
                    ->label('Cheque No'),
                Tables\Columns\TextColumn::make('transaction_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'cleared' => 'success',
                        'bounced' => 'danger',
                        default => 'gray',
                    }),
            ]);
    }
}
