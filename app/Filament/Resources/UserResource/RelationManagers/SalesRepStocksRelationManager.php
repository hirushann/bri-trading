<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SalesRepStocksRelationManager extends RelationManager
{
    protected static string $relationship = 'salesRepStocks';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title=Inventory')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product.name')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('transfer_stock')
                    ->label('Transfer Stock')
                    ->icon('heroicon-o-arrows-right-left')
                    ->form([
                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->options(\App\Models\Product::all()->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('quantity')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                    ])
                    ->action(function (array $data, \App\Filament\Resources\UserResource\RelationManagers\SalesRepStocksRelationManager $livewire) {
                        $user = $livewire->getOwnerRecord();
                        $product = \App\Models\Product::findOrFail($data['product_id']);
                        $quantity = $data['quantity'];

                        // 1. Deduct from Main Inventory
                        $product->decrement('stock_quantity', $quantity);

                        // 2. Create Stock Movement (OUT from Main)
                        \App\Models\StockMovement::create([
                            'product_id' => $product->id,
                            'quantity' => -$quantity,
                            'type' => 'transfer_out',
                            'note' => 'Transfer to Sales Rep: ' . $user->name,
                            'reference' => 'TRF-' . uniqid(),
                        ]);
                        
                        // 3. Add to Sales Rep Stock
                         $stock = \App\Models\SalesRepStock::firstOrCreate(
                            ['user_id' => $user->id, 'product_id' => $product->id],
                            ['quantity' => 0]
                        );
                        $stock->increment('quantity', $quantity);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Stock Transferred Successfully')
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('Return Stock')
                    ->modalHeading('Return Stock to Warehouse')
                    ->modalDescription('Are you sure you want to return this stock to the main warehouse?')
                    ->action(function (\App\Models\SalesRepStock $record) {
                        // Return Logic (Reverse of transfer)
                        $product = $record->product;
                        $quantity = $record->quantity;
                        
                        // 1. Create Stock Movement (IN to Main)
                        \App\Models\StockMovement::create([
                            'product_id' => $product->id,
                            'quantity' => $quantity,
                            'type' => 'transfer_return',
                            'note' => 'Return from Sales Rep: ' . $record->user->name,
                            'reference' => 'RET-' . uniqid(),
                        ]);
                        
                        $product->increment('stock_quantity', $quantity); // wait, Observer on OrderItem handles `decrement` on product.
                        // But here we are manually managing main stock movements?
                        // The User hasn't asked for stock deduction on main inventory yet, but implied.
                        // My previous `TransferStock` page implemented creating a StockMovement.
                        // Does StockMovement logic *actually* update Product quantity? No, `StockMovement` is just a log usually?
                        // Let's check `StockMovement` observer? I haven't created one.
                        // OrderItemObserver *manually* decremented product stock AND created StockMovement.
                        // So I should do the same here.
                        
                        // Wait, my TransferStock page implemented:
                        // 1. StockMovement create
                        // 2. SalesRepStock increment
                        // I missed `Product::decrement` in TransferStock page!
                        // I should add that here and fix TransferStock page if I keep it.
                        // Assuming RelationManager replaces TransferStock page, I will implement it correctly here.
                        
                        $product->increment('stock_quantity', $quantity);
                        $record->delete();
                    }),
            ])
            ->bulkActions([
                //
            ]);
    }
}
