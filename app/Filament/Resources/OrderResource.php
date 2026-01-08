<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
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
                                    ->options([
                                        'pending' => 'Pending',
                                        'confirmed' => 'Confirmed',
                                        'completed' => 'Completed',
                                        'cancelled' => 'Cancelled',
                                    ])
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
                            ])->columns(2),

                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\Repeater::make('items')
                                    ->relationship()
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
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference')
                    ->searchable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
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
                Tables\Actions\Action::make('update_status')
                    ->label('Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->fillForm(fn (Order $record): array => [
                        'status' => $record->status,
                    ])
                    ->form([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'confirmed' => 'Confirmed',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data) {
                        $record->update(['status' => $data['status']]);
                    }),
                Tables\Actions\Action::make('print_invoice')
                    ->label('Print Invoice')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Order $record) => $record->invoice ? route('invoices.print', $record->invoice) : null)
                    ->openUrlInNewTab()
                    ->visible(fn (Order $record) => $record->invoice()->exists()),
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
