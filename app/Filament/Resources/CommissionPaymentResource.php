<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommissionPaymentResource\Pages;
use App\Filament\Resources\CommissionPaymentResource\RelationManagers;
use App\Models\CommissionPayment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CommissionPaymentResource extends Resource
{
    protected static ?string $model = CommissionPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';
    
    protected static ?string $navigationLabel = 'Commission Payments';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship(
                        'user',
                        'name',
                        fn (Builder $query) => $query->where(function (Builder $q) {
                            $q->whereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', ['Sales Representative', 'Admin']))
                                ->orWhereHas('commissions');
                        })
                    )
                    ->label('Sales Rep')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->prefix('LKR')
                    ->minValue(0.01)
                    // Can't pay more than the rep is owed (on edit, this payment's own amount is owed again).
                    ->maxValue(fn (Forms\Get $get, ?\App\Models\CommissionPayment $record) => round(
                        (float) \App\Models\Commission::where('user_id', $get('user_id'))->whereIn('status', ['pending', 'partially_paid'])->sum(\DB::raw('amount - paid_amount'))
                        + ($record && $record->user_id == $get('user_id') ? (float) $record->amount : 0),
                        2
                    ))
                    ->validationMessages(['max' => 'This rep is only owed LKR :max in commission.'])
                    ->required(),
                Forms\Components\DatePicker::make('payment_date')
                    ->default(now())
                    ->required(),
                Forms\Components\TextInput::make('reference')
                    ->label('Payment Reference (Optional)'),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Sales Rep')
                    ->relationship('user', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListCommissionPayments::route('/'),
            'create' => Pages\CreateCommissionPayment::route('/create'),
            'edit' => Pages\EditCommissionPayment::route('/{record}/edit'),
        ];
    }
}
