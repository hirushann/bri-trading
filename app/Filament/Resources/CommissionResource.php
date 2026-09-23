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
                        'partially_paid' => 'Partially Paid',
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
                Tables\Columns\TextColumn::make('paid_amount')
                    ->money('LKR')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('LKR')),
                Tables\Columns\TextColumn::make('due_amount')
                    ->money('LKR')
                    ->state(fn (Commission $record): float => $record->due_amount)
                    ->sortable(query: fn (\Illuminate\Database\Eloquent\Builder $query, string $direction) => $query->orderByRaw('amount - paid_amount ' . $direction))
                    ->summarize(
                        Tables\Columns\Summarizers\Summarizer::make()
                            ->label('Sum')
                            ->money('LKR')
                            ->using(fn (\Illuminate\Database\Query\Builder $query): float => (float) $query->sum(\Illuminate\Support\Facades\DB::raw('amount - paid_amount')))
                    ),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'partially_paid' => 'info',
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
                Tables\Filters\Filter::make('unpaid')
                    ->label('Show Only Unpaid')
                    ->query(fn (Builder $query): Builder => $query->whereIn('status', ['pending', 'partially_paid']))
                    ->default(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'partially_paid' => 'Partially Paid',
                        'paid' => 'Paid',
                    ]),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Sales Rep')
                    ->relationship(
                        'user',
                        'name',
                        fn (Builder $query) => $query->where(function (Builder $q) {
                            $q->whereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', ['Sales Representative', 'Admin']))
                                ->orWhereHas('commissions');
                        })
                    )
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('From ' . \Carbon\Carbon::parse($data['created_from'])->toFormattedDateString())
                                ->removeField('created_from');
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Until ' . \Carbon\Carbon::parse($data['created_until'])->toFormattedDateString())
                                ->removeField('created_until');
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('mark_as_paid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Commission $record) => in_array($record->status, ['pending', 'partially_paid']))
                    ->action(fn (Commission $record) => $record->update(['status' => 'paid', 'paid_at' => now(), 'paid_amount' => $record->amount])),
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
                        ->action(fn (\Illuminate\Database\Eloquent\Collection $records) => $records->each(fn ($record) => $record->update(['status' => 'paid', 'paid_at' => now(), 'paid_amount' => $record->amount]))),
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
