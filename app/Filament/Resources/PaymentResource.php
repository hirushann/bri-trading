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
                    ->afterStateUpdated(function (Forms\Set $set) {
                        $set('invoice_id', null);
                        $set('invoice_ids', []);
                    })
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
                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('amount', \App\Models\Invoice::find($state)?->balance_due ?? 0))
                    ->hiddenOn('create'),
                // On create, one payment can cover several invoices; CreatePayment splits it into one Payment per invoice.
                Forms\Components\Select::make('invoice_ids')
                    ->label('Invoices')
                    ->multiple()
                    ->options(fn (Forms\Get $get) => \App\Models\Invoice::query()
                        ->where('balance_due', '>', 0)
                        ->when(
                            $get('customer_id'),
                            fn (Builder $query) => $query->whereHas('order', fn (Builder $q) => $q->where('customer_id', $get('customer_id')))
                        )
                        ->get()
                        ->mapWithKeys(fn ($invoice) => [$invoice->id => "{$invoice->invoice_number} (Due: LKR " . number_format($invoice->balance_due, 2) . ')']))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('amount', \App\Models\Invoice::whereIn('id', $state ?? [])->sum('balance_due')))
                    ->visibleOn('create'),
                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('LKR'),
                Forms\Components\TextInput::make('discount')
                    ->label('Discount / Waived')
                    ->numeric()
                    ->minValue(0)
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
                    ->options(Payment::STATUSES)
                    ->helperText('Bounced cheques do not count towards the invoice or commission.')
                    ->required()
                    ->default('pending'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('invoice.order.customer'))
            ->defaultSort('transaction_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice.order.customer.name')
                    ->label('Customer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('LKR')
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount')
                    ->label('Waived')
                    ->money('LKR')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('method')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->icon(fn (string $state) => $state === 'cheque' ? 'heroicon-m-document-text' : 'heroicon-m-banknotes')
                    ->color('gray'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Payment::STATUSES[$state] ?? ucfirst($state))
                    ->color(fn (string $state) => Payment::STATUS_COLORS[$state] ?? 'gray'),
                Tables\Columns\TextColumn::make('cheque_number')
                    ->label('Cheque No.')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cheque_date')
                    ->date('M j, Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(Payment::STATUSES),
                Tables\Filters\SelectFilter::make('method')->options(['cash' => 'Cash', 'cheque' => 'Cheque']),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_cleared')
                    ->label('Cleared')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mark this cheque as cleared?')
                    ->visible(fn (Payment $record) => $record->method === 'cheque' && $record->status === 'pending')
                    ->action(fn (Payment $record) => $record->update(['status' => 'cleared'])),
                Tables\Actions\Action::make('mark_bounced')
                    ->label('Bounced')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Mark this cheque as bounced?')
                    ->modalDescription('The amount goes back on the invoice and the rep\'s unpaid commission for it is removed.')
                    ->visible(fn (Payment $record) => $record->method === 'cheque' && $record->status === 'pending')
                    ->action(fn (Payment $record) => $record->update(['status' => 'bounced'])),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('print_receipt')
                        ->label('Print Receipt')
                        ->icon('heroicon-o-printer')
                        ->url(fn (Payment $record) => route('payments.print', $record))
                        ->openUrlInNewTab(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No payments yet')
            ->emptyStateDescription('Record a payment when a customer pays one or more invoices.')
            ->emptyStateIcon('heroicon-o-banknotes');
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
