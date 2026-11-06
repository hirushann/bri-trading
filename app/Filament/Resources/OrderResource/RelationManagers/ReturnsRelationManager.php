<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Filament\Resources\OrderResource;
use App\Models\OrderReturn;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ReturnsRelationManager extends RelationManager
{
    protected static string $relationship = 'returns';

    protected static ?string $title = 'Returns';

    protected static ?string $icon = 'heroicon-o-arrow-uturn-left';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('items.orderItem.product'))
            ->defaultSort('return_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('return_date')
                    ->label('Date')
                    ->date('M j, Y'),
                Tables\Columns\TextColumn::make('items')
                    ->label('Items returned')
                    ->state(fn (OrderReturn $record) => $record->items->map(
                        fn ($item) => $item->quantity . ' × ' . ($item->orderItem?->product?->name ?? 'Deleted product')
                    )->all())
                    ->listWithLineBreaks()
                    ->bulleted(),
                Tables\Columns\TextColumn::make('restock_to')
                    ->label('Stock back to')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'rep' ? 'Sales rep' : 'Main stock'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Credited')
                    ->money('LKR')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('reason')
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('Undo')
                    ->icon('heroicon-o-arrow-uturn-right')
                    ->modalHeading('Undo this return?')
                    ->modalDescription('The returned stock is taken out again and the amount goes back on what the customer owes.')
                    ->successNotificationTitle('Return undone')
                    ->authorize(fn () => auth()->user()->can('update', $this->getOwnerRecord()))
                    // Reload so the order's status field is not stale.
                    ->after(fn () => $this->redirect(OrderResource::getUrl('edit', ['record' => $this->getOwnerRecord()]))),
            ])
            ->emptyStateHeading('No returns')
            ->emptyStateDescription('Use "Return Items" at the top of the page if the customer sends anything back.')
            ->emptyStateIcon('heroicon-o-arrow-uturn-left');
    }
}
