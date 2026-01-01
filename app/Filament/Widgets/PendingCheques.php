<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingCheques extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Payment::query()
                    ->where('method', 'cheque')
                    ->where('status', 'pending')
                    ->with('invoice')
                    ->orderBy('cheque_date', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('LKR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('cheque_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cheque_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('transaction_date')
                    ->date()
                    ->label('Paid On'),
            ]);
    }
}
