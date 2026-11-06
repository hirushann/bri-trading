<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Models\Payment;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingCheques extends BaseWidget
{
    use UsesDashboardFilters;

    protected static ?int $sort = 6;

    protected static ?string $heading = 'Cheques to Bank';

    public function table(Table $table): Table
    {
        return $table
            ->description('Pending cheques, earliest date first. Red ones are already due.')
            // All pending cheques, not just the period: they still need action. Rep/customer filters apply.
            ->query(
                $this->payments()
                    ->where('method', 'cheque')
                    ->where('status', 'pending')
                    ->with('invoice.order.customer')
            )
            ->defaultSort('cheque_date', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('cheque_date')
                    ->label('Cheque date')
                    ->date('M j, Y')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : null)
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice.order.customer.name')
                    ->label('Customer'),
                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Bank')
                    ->description(fn (Payment $record) => $record->cheque_number)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('LKR')
                    ->alignEnd()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('cleared')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mark this cheque as cleared?')
                    ->authorize(fn (Payment $record) => auth()->user()->can('update', $record))
                    ->action(function (Payment $record) {
                        $record->update(['status' => 'cleared']);
                        Notification::make()->title('Cheque cleared')->success()->send();
                    }),
                Tables\Actions\Action::make('bounced')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Mark this cheque as bounced?')
                    ->modalDescription('The amount goes back on the invoice and the rep\'s unpaid commission for it is removed.')
                    ->authorize(fn (Payment $record) => auth()->user()->can('update', $record))
                    ->action(function (Payment $record) {
                        $record->update(['status' => 'bounced']);
                        Notification::make()->title('Cheque marked as bounced')->warning()->send();
                    }),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('No cheques waiting')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
