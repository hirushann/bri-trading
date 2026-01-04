<x-filament-panels::page>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        @foreach($products as $product)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow dark:bg-gray-900 dark:border-gray-800">
                <div class="aspect-square w-full bg-gray-100 relative overflow-hidden group">
                    @if($product->image)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($product->image) }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="{{ $product->name }}">
                    @else
                       <div class="flex items-center justify-center h-full text-gray-400">
                           <x-heroicon-o-photo class="w-16 h-16" />
                       </div>
                    @endif
                    <div class="absolute top-2 right-2">
                         @if($product->stock_quantity > 0)
                            <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20">In Stock</span>
                        @else
                            <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20">Out of Stock</span>
                        @endif
                    </div>
                </div>

                <div class="p-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white truncate">{{ $product->name }}</h3>
                    <p class="text-sm text-gray-500 mb-2">{{ $product->sku ?? 'N/A' }}</p>
                    
                    <div class="flex items-baseline gap-1 mt-2">
                        <span class="text-2xl font-bold text-primary-600">LKR {{ number_format($product->price, 2) }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
