<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PriceList extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public $products;

    public function mount(): void
    {
        $this->products = \App\Models\Product::where('stock_quantity', '>', 0)->get();
    }

    protected static string $view = 'filament.pages.price-list';
}
