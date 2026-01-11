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

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Finance';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->label('Sales Rep')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->prefix('LKR')
                    ->required(),
                Forms\Components\Select::make('payment_id')
                    ->relationship(
                        name: 'payment',
                        titleAttribute: 'id',
                        modifyQueryUsing: fn (Builder $query) => $query->with('invoice'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (\App\Models\Payment $record) => "Payment #{$record->id} - Invoice #{$record->invoice?->invoice_number} (LKR " . number_format($record->amount, 2) . ")")
                    ->label('Payment Reference')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                    ])
                    ->required()
                    ->default('pending')
                    ->live(),
                Forms\Components\DatePicker::make('paid_at')
                    ->visible(fn (Forms\Get $get) => $get('status') === 'paid')
                    ->required(fn (Forms\Get $get) => $get('status') === 'paid')
                    ->default(now()),
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
                Tables\Columns\TextColumn::make('payment.invoice.invoice_number')
                    ->label('Invoice')
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
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('mark_as_paid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Commission $record) => $record->status === 'pending')
                    ->action(fn (Commission $record) => $record->update(['status' => 'paid', 'paid_at' => now()])),
                Tables\Actions\Action::make('print_receipt')
                    ->label('Receipt')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Commission $record) => route('commissions.print', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Commission $record) => $record->status === 'paid'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('mark_as_paid_bulk')
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
