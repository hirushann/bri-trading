<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class IncentivePayable extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->whereHas('commissions', fn (Builder $query) => $query->where('status', 'pending'))
                    ->withSum(['commissions as pending_commission' => fn ($query) => $query->where('status', 'pending')], 'amount')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Sales Rep')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email'),
                Tables\Columns\TextColumn::make('pending_commission')
                    ->label('Pending Commission')
                    ->money('LKR')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('pay')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Pay Commission')
                    ->modalDescription('Are you sure you want to mark all pending commissions for this sales rep as paid?')
                    ->modalSubmitActionLabel('Yes, Mark as Paid')
                    ->action(function (User $record) {
                        $count = $record->commissions()->where('status', 'pending')->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                        ]);

                        Notification::make()
                            ->title("Paid {$count} commission records for {$record->name}")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
