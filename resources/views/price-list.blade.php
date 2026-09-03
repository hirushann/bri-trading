<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Price List - BRI Trading</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            -webkit-print-color-adjust: exact; 
            print-color-adjust: exact;
        }
        
        .price-list-table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            border: none;
        }

        .price-list-table thead {
            display: table-header-group;
        }

        .price-list-table th.header-th,
        .price-list-table td.content-td {
            padding: 0;
            border: none;
            font-weight: normal;
            text-align: left;
        }

        .price-list-header {
            background-color: #ffffff;
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 32px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
        }

        @media screen and (max-width: 640px) {
            .price-list-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 20px;
                gap: 16px;
            }
            .header-right {
                text-align: left;
                width: 100%;
            }
        }

        @media print {
            body {
                background-color: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .no-print { 
                display: none !important; 
            }
            .price-list-table {
                width: 100% !important;
                border-collapse: collapse !important;
            }
            .price-list-table thead {
                display: table-header-group !important;
            }
            .price-list-table tbody tr,
            .price-list-table td.content-td {
                break-inside: auto !important;
                page-break-inside: auto !important;
            }
            .price-list-table th.header-th {
                padding-bottom: 20px !important;
            }
            .price-list-header {
                background-color: transparent !important;
                border: none !important;
                border-bottom: 2px solid #111827 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                padding: 4px 0 16px 0 !important;
                margin-bottom: 0 !important;
                display: flex !important;
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: center !important;
            }
            .print-break-inside-avoid { 
                break-inside: avoid !important; 
                page-break-inside: avoid !important; 
            }
            .print-force-3-cols { 
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important; 
            }
            @page {
                margin: 12mm 10mm;
                size: portrait;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto">
        <table class="price-list-table">
            <thead>
                <tr>
                    <th class="header-th">
                        <!-- Company & Price List Header -->
                        <header class="price-list-header">
                            <!-- Company Info with Logo -->
                            <div class="flex items-center gap-4 sm:gap-5">
                                <img src="{{ asset('img/BRI_Logo.png') }}" alt="BRI Trading Logo" class="h-14 sm:h-16 w-auto object-contain shrink-0" style="max-height: 64px;">
                                <div class="border-l-2 border-gray-200 pl-4 sm:pl-5">
                                    <h1 class="text-lg sm:text-xl font-bold text-gray-900 leading-tight">BRI Trading (Pvt) Ltd</h1>
                                    <p class="text-xs sm:text-sm text-gray-600 font-medium mt-1">NO: 66/4/2 WEBADAGALLA, NITTAMBUWA.</p>
                                    <p class="text-xs sm:text-sm text-gray-600 font-medium">+94777800611 / +94701260560</p>
                                </div>
                            </div>

                            <!-- Document Details & Actions -->
                            <div class="header-right text-right shrink-0">
                                <span class="text-[11px] uppercase tracking-wider font-semibold text-blue-600 block">Official Catalog</span>
                                <h2 class="text-xl sm:text-2xl font-extrabold text-gray-900 tracking-tight whitespace-nowrap">PRODUCT PRICE LIST</h2>
                                <!-- <p class="text-xs text-gray-500 mt-1 font-medium">Date: {{ now()->format('d M Y') }}</p> -->

                                <div class="no-print mt-3">
                                    <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs sm:text-sm font-semibold rounded-lg shadow-sm hover:shadow transition-all duration-200 cursor-pointer">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                                        </svg>
                                        <span>Print / Save as PDF</span>
                                    </button>
                                </div>
                            </div>
                        </header>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="content-td">
                        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6 print-force-3-cols">
                            @foreach($products as $product)
                                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow duration-300 flex flex-col print-break-inside-avoid">
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
                                        <div class="absolute top-2 right-2 no-print">
                                             @if($product->stock_quantity > 0)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                                    {{ $product->stock_quantity }} in stock
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                                    Out of Stock
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="p-5 flex flex-col flex-1">
                                        <div class="flex-1">
                                            <h3 class="text-lg font-bold text-gray-900 mb-1">{{ $product->name }}</h3>
                                            <span class="text-base font-mono text-gray-400">{{ $product->sku ?? 'NO-CODE' }}</span>
                                            <p class="text-sm text-gray-500 line-clamp-2 mb-4">{{ $product->description }}</p>
                                        </div>
                                        
                                        <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-center">
                                            <span class="text-xl font-bold text-blue-600">LKR {{ number_format($product->price, 2) }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
