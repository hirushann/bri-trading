<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommissionResource\Pages;
use App\Filament\Resources\CommissionResource\RelationManagers;
use App\Models\Commission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CommissionResource extends Resource
{
    protected static ?string $model = Commission::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Sales Rep')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('LKR')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('LKR')),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'paid' => 'success',
                    }),
                Tables\Columns\TextColumn::make('payment.id')
                    ->label('Payment ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                    ]),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Sales Rep')
                    ->relationship('user', 'name'),
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_as_paid')
                        ->label('Mark as Paid')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (\Illuminate\Database\Eloquent\Collection $records) => $records->each->update(['status' => 'paid', 'paid_at' => now()])),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommissions::route('/'),
            'create' => Pages\CreateCommission::route('/create'),
            'edit' => Pages\EditCommission::route('/{record}/edit'),
        ];
    }
}
