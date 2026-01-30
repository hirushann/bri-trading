<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Filament\Resources\PaymentResource\RelationManagers;
use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    
    protected static ?string $navigationGroup = 'Finance';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('customer_id')
                    ->label('Customer')
                    ->options(\App\Models\Customer::pluck('name', 'id'))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn (Forms\Set $set) => $set('invoice_id', null))
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Forms\Components\Select $component, ?Payment $record) {
                        if ($record?->invoice?->order?->customer_id) {
                            $component->state($record->invoice->order->customer_id);
                        }
                    }),
                Forms\Components\Select::make('invoice_id')
                    ->options(fn (Forms\Get $get) => \App\Models\Invoice::query()
                        ->when(
                            $get('customer_id'),
                            fn (Builder $query) => $query->whereHas('order', fn (Builder $q) => $q->where('customer_id', $get('customer_id')))
                        )
                        ->pluck('invoice_number', 'id'))
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('amount', \App\Models\Invoice::find($state)?->balance_due ?? 0)),
                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->prefix('LKR'),
                Forms\Components\TextInput::make('discount')
                    ->label('Discount / Waived')
                    ->numeric()
                    ->prefix('LKR')
                    ->default(0),
                Forms\Components\Select::make('method')
                    ->options([
                        'cash' => 'Cash',
                        'cheque' => 'Cheque',
                    ])
                    ->required()
                    ->live(),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')
                            ->required(),
                        Forms\Components\TextInput::make('branch')
                            ->required(),
                        Forms\Components\TextInput::make('cheque_number')
                            ->required(),
                        Forms\Components\DatePicker::make('cheque_date')
                            ->required(),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('method') === 'cheque')
                    ->columns(2)
                    ->columnSpanFull(),
                Forms\Components\DatePicker::make('transaction_date')
                    ->default(now())
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'cleared' => 'Cleared',
                        'bounced' => 'Bounced',
                    ])
                    ->required()
                    ->default('pending'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Invoice Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('method')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cheque_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cheque_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('transaction_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('print_receipt')
                    ->label('Print Receipt')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Payment $record) => route('payments.print', $record))
                    ->openUrlInNewTab(),
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
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
