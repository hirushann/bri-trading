<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use App\Models\OrderReturn;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    
    protected static ?string $navigationGroup = 'Sales';

    public static function updateTotals(Forms\Get $get, Forms\Set $set): void
    {
        // Try to get items from current scope (root) or parent scope (from inside repeater)
        $items = $get('items') ?? $get('../../items') ?? [];
        
        $subtotal = collect($items)->sum(fn ($item) => $item['subtotal'] ?? 0);
        
        // Try to get discount from current or parent
        $discount = $get('discount') ?? $get('../../discount') ?? 0;
        $type = $get('discount_type') ?? $get('../../discount_type');
        
        $discountAmount = 0;
        if ($type === 'percentage') {
            $discountAmount = $subtotal * ($discount / 100);
        } else {
            $discountAmount = $discount;
        }
        
        $grandTotal = max(0, $subtotal - $discountAmount);
        
        // Set values. Note: In Filament, $set with relative path ../../ might be needed if inside repeater.
        // We set both to cover all cases, as setting a non-existent field usually does nothing or creates temporary state.
        $set('total_amount', $grandTotal);
        $set('../../total_amount', $grandTotal);
        
        $set('subtotal_display', number_format($subtotal, 2) . ' LKR');
        $set('../../subtotal_display', number_format($subtotal, 2) . ' LKR');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\Select::make('customer_id')
                                    ->relationship('customer', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Forms\Components\Placeholder::make('reference')
                                    ->label('Reference')
                                    ->content(function (?Order $record) {
                                        if ($record) {
                                            return $record->reference;
                                        }
                                        
                                        $nextId = (Order::max('id') ?? 0) + 1;
                                        return 'BRI-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                                    }),
                                Forms\Components\DatePicker::make('date')
                                    ->default(now())
                                    ->required(),
                                Forms\Components\Select::make('status')
                                    ->options(Order::STATUSES)
                                    ->required()
                                    ->default('pending'),
                                Forms\Components\Select::make('sales_rep_id')
                                    ->label('Sales Rep')
                                    ->relationship('salesRep', 'name')
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Toggle::make('from_sales_rep_stock')
                                    ->label('Fulfill from Rep Stock')
                                    ->default(false),
                                Forms\Components\TextInput::make('tracking_number')
                                    ->label('Tracking Number')
                                    ->maxLength(255),
                            ])->columns(2),

                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\Repeater::make('items')
                                    ->relationship()
                                    // Changing items after a return would restock twice and break the return totals.
                                    ->disabled(fn (?Order $record) => $record?->returns()->exists())
                                    ->helperText(fn (?Order $record) => $record?->returns()->exists() ? 'Items are locked because this order has returns. Undo the returns first to change items.' : null)
                                    ->schema([
                                        Forms\Components\Select::make('product_id')
                                            ->relationship('product', 'name')
                                            ->searchable(['name', 'sku'])
                                            ->preload()
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                                $price = \App\Models\Product::find($state)?->price ?? 0;
                                                $set('unit_price', $price);
                                                $quantity = $get('quantity') ?? 1;
                                                $set('subtotal', $price * $quantity);
                                                self::updateTotals($get, $set);
                                            })
                                            ->distinct()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->columnSpan([
                                                'md' => 4,
                                            ]),
                                        Forms\Components\TextInput::make('quantity')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1)
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                                $set('subtotal', $state * $get('unit_price'));
                                                self::updateTotals($get, $set);
                                            })
                                            ->columnSpan([
                                                'md' => 2,
                                            ]),
                                        Forms\Components\TextInput::make('unit_price')
                                            ->label('Unit Price')
                                            ->numeric()
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                                $set('subtotal', $state * $get('quantity'));
                                                self::updateTotals($get, $set);
                                            })
                                            ->columnSpan([
                                                'md' => 3,
                                            ]),
                                        Forms\Components\TextInput::make('subtotal')
                                            ->numeric()
                                            ->required()
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan([
                                                'md' => 3,
                                            ]),
                                    ])
                                    ->defaultItems(1)
                                    ->columns([
                                        'md' => 12,
                                    ])
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                        self::updateTotals($get, $set);
                                    }),
                            ]),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Order Summary')
                            ->schema([
                                Forms\Components\Select::make('discount_type')
                                    ->options([
                                        'fixed' => 'Fixed Amount',
                                        'percentage' => 'Percentage',
                                    ])
                                    ->default('fixed')
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                        self::updateTotals($get, $set);
                                    }),
                                Forms\Components\TextInput::make('discount')
                                    ->numeric()
                                    ->required()
                                    ->label(fn (Forms\Get $get) => $get('discount_type') === 'percentage' ? 'Discount Percentage' : 'Discount Amount')
                                    ->suffix(fn (Forms\Get $get) => $get('discount_type') === 'percentage' ? '%' : 'LKR')
                                    ->default(0.00)
                                    ->minValue(0)
                                    ->maxValue(fn (Forms\Get $get) => $get('discount_type') === 'percentage' ? 100 : null)
                                    ->live()
                                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                        self::updateTotals($get, $set);
                                    }),
                                Forms\Components\Placeholder::make('subtotal_display')
                                    ->label('Subtotal')
                                    ->content(fn (Forms\Get $get) => number_format(collect($get('items'))->sum(fn ($item) => $item['subtotal'] ?? 0), 2) . ' LKR'),
                                Forms\Components\TextInput::make('total_amount')
                                    ->label('Grand Total')
                                    ->numeric()
                                    ->required()
                                    ->readOnly()
                                    ->prefix('LKR')
                                    ->default(0.00)
                                    ->dehydrated() // Ensure it saves
                                    ->extraInputAttributes(['class' => 'text-xl font-bold']),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer', 'invoice']))
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (Order $record) => static::getUrl('edit', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Order')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('LKR')
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance')
                    ->label('Due')
                    ->money('LKR')
                    ->alignEnd()
                    // balance_due is kept by Invoice::recalculateBalance (discounts and returns in, bounced cheques out).
                    ->state(fn (Order $record) => $record->invoice?->balance_due ?? $record->total_amount)
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Order::STATUSES[$state] ?? ucfirst($state))
                    ->color(fn (string $state) => Order::STATUS_COLORS[$state] ?? 'gray'),
                Tables\Columns\TextColumn::make('tracking_number')
                    ->label('Tracking No.')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),
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
                Tables\Filters\SelectFilter::make('status')
                    ->options(Order::STATUSES)
                    ->multiple(),
                Tables\Filters\SelectFilter::make('customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),
                Tables\Filters\Filter::make('hide_completed')
                    ->label('Hide Completed Orders')
                    ->query(fn (Builder $query): Builder => $query->where('status', '!=', 'completed'))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('update_status')
                    ->label('Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->modalWidth('md')
                    ->modalSubmitActionLabel('Save')
                    ->fillForm(fn (Order $record): array => [
                        'status' => $record->status,
                    ])
                    ->form([
                        Forms\Components\Select::make('status')
                            ->options(Order::STATUSES)
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data) {
                        $record->update(['status' => $data['status']]);
                        Notification::make()->title('Status updated')->success()->send();
                    }),
                Tables\Actions\Action::make('update_tracking')
                    ->label('Tracking')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->modalWidth('md')
                    ->modalSubmitActionLabel('Save')
                    ->fillForm(fn (Order $record): array => [
                        'tracking_number' => $record->tracking_number,
                    ])
                    ->form([
                        Forms\Components\TextInput::make('tracking_number')
                            ->label('Tracking Number')
                            ->maxLength(255),
                    ])
                    ->action(function (Order $record, array $data) {
                        $record->update(['tracking_number' => $data['tracking_number']]);
                        Notification::make()->title('Tracking number saved')->success()->send();
                    }),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    static::returnAction(),
                    Tables\Actions\Action::make('print_invoice')
                        ->label('Print Invoice')
                        ->icon('heroicon-o-printer')
                        ->url(fn (Order $record) => $record->invoice ? route('invoices.print', $record->invoice) : null)
                        ->openUrlInNewTab()
                        ->visible(fn (Order $record) => $record->invoice !== null),
                    Tables\Actions\Action::make('print_shipping_label')
                        ->label('Shipping Label')
                        ->icon('heroicon-o-tag')
                        ->url(fn (Order $record) => route('orders.shipping-label', $record))
                        ->openUrlInNewTab(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No orders yet')
            ->emptyStateDescription('Create your first order to start tracking sales.')
            ->emptyStateIcon('heroicon-o-shopping-cart');
    }

    /**
     * Return some or all items of an order. Used from the orders list and the order edit page.
     */
    public static function returnAction(string $class = Tables\Actions\Action::class): Tables\Actions\Action|\Filament\Actions\Action
    {
        return $class::make('return_items')
            ->label('Return Items')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->modalHeading(fn (Order $record) => "Return items from {$record->reference}")
            ->modalDescription('Enter how many of each item came back. Stock is added back and the value is taken off what the customer owes.')
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Record Return')
            ->visible(fn (Order $record) => ! in_array($record->status, ['returned', 'cancelled']) && auth()->user()->can('update', $record))
            ->fillForm(fn (Order $record): array => [
                'return_date' => now()->toDateString(),
                'restock_to' => 'main',
                'items' => $record->items()->with('product')->get()->map(fn ($item) => [
                    'order_item_id' => $item->id,
                    'product' => $item->product?->name ?? 'Deleted product',
                    'sold' => $item->quantity,
                    'available' => $item->quantity - $item->returnedQuantity(),
                    'quantity' => 0,
                ])->all(),
            ])
            ->form([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\DatePicker::make('return_date')
                        ->label('Return date')
                        ->required(),
                    Forms\Components\Select::make('restock_to')
                        ->label('Put stock back into')
                        ->options(fn (Order $record) => array_filter([
                            'main' => 'Main stock',
                            'rep' => $record->sales_rep_id ? 'Sales rep stock (' . $record->salesRep?->name . ')' : null,
                        ]))
                        ->required(),
                ]),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('return_everything')
                        ->label('Return everything')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->link()
                        ->action(function (Forms\Get $get, Forms\Set $set) {
                            foreach ($get('items') ?? [] as $key => $row) {
                                $set("items.{$key}.quantity", $row['available']);
                            }
                        }),
                ]),
                Forms\Components\Repeater::make('items')
                    ->hiddenLabel()
                    ->schema([
                        Forms\Components\Hidden::make('order_item_id'),
                        Forms\Components\TextInput::make('product')->disabled()->dehydrated(false)->columnSpan(3),
                        Forms\Components\TextInput::make('sold')->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('available')->label('Can return')->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('quantity')
                            ->label('Return qty')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(fn (Forms\Get $get) => (int) $get('available'))
                            ->required(),
                    ])
                    ->columns(6)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false),
                Forms\Components\Textarea::make('reason')
                    ->placeholder('Damaged, wrong item, customer changed mind...')
                    ->rows(2),
            ])
            ->action(function (Order $record, array $data, $action) {
                $quantities = collect($data['items'])->pluck('quantity', 'order_item_id')->all();

                try {
                    $return = OrderReturn::record($record, $quantities, $data);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                    $action->halt();
                }

                $record->refresh();
                $notification = Notification::make()
                    ->title('Return recorded: LKR ' . number_format($return->amount, 2))
                    ->body($record->status === 'returned' ? 'The whole order has been returned.' : null)
                    ->success();

                // If the customer had already paid for what came back, they are owed money.
                $invoice = $record->invoice;
                if ($invoice) {
                    $paid = $invoice->payments()->where('status', '!=', 'bounced')->sum('amount');
                    $owedBack = $paid - ($invoice->total_amount - $record->returns()->sum('amount'));
                    if ($owedBack > 0) {
                        $notification->warning()->persistent()
                            ->body('The customer has paid LKR ' . number_format($owedBack, 2) . ' more than they now owe. Refund or credit it to them.');
                    }
                }

                $notification->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ReturnsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
