<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\Page;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;

class TransferStock extends Page
{
    protected static string $resource = UserResource::class;

    protected static string $view = 'filament.resources.user-resource.pages.transfer-stock';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Sales Representative')
                    ->options(\App\Models\User::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
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
            ->statePath('data');
    }

    public function create(): void
    {
        $data = $this->form->getState();
        
        $user = \App\Models\User::findOrFail($data['user_id']);
        $product = \App\Models\Product::findOrFail($data['product_id']);
        $quantity = $data['quantity'];

        // 1. Create Stock Movement (OUT)
        \App\Models\StockMovement::create([
            'product_id' => $product->id,
            'quantity' => -$quantity,
            'type' => 'transfer_out',
            'note' => 'Transfer to Sales Rep: ' . $user->name,
            'reference' => 'TRF-' . uniqid(),
        ]);
        
        // 2. Add to Sales Rep Stock
        $stock = \App\Models\SalesRepStock::firstOrCreate(
            ['user_id' => $user->id, 'product_id' => $product->id],
            ['quantity' => 0]
        );
        $stock->increment('quantity', $quantity);

        Notification::make()
            ->success()
            ->title('Stock Transferred Successfully')
            ->send();
            
        $this->form->fill();
    }
}
