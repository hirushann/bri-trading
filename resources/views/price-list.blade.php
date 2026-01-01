<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Price List - BRI Trading</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen p-6">
    <div class="max-w-7xl mx-auto">
        <header class="mb-10 text-center">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">Product Price List</h1>
            <p class="text-gray-500">Current stock and pricing available at BRI Trading.</p>
        </header>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            @foreach($products as $product)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow duration-300 flex flex-col">
                    <div class="aspect-square w-full bg-gray-100 relative overflow-hidden group">
                        @if($product->image)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($product->image) }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="{{ $product->name }}">
                        @else
                            <div class="flex items-center justify-center h-full text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                            </div>
                        @endif
                        <div class="absolute top-2 right-2">
                             @if($product->stock_quantity > 0)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    {{ $product->stock_quantity }} in stock
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    Out of Stock
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="p-5 flex flex-col flex-1">
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-gray-900 mb-1">{{ $product->name }}</h3>
                            <p class="text-sm text-gray-500 line-clamp-2 mb-4">{{ $product->description }}</p>
                        </div>
                        
                        <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between">
                            <span class="text-xs font-mono text-gray-400">{{ $product->code ?? 'NO-CODE' }}</span>
                            <span class="text-2xl font-bold text-blue-600">LKR {{ number_format($product->price, 2) }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</body>
</html>
