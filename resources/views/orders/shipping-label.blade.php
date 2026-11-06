<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Label - Order #{{ $order->reference }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @page {
            size: A5 landscape;
            margin: 0;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white;
            }
            .label-container {
                box-shadow: none;
                border: none;
                margin: 0;
                width: 210mm;
                height: 148mm;
            }
        }
        body {
            background-color: #f3f4f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .label-container {
            background: white;
            width: 210mm;
            height: 148mm;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 15mm;
            box-sizing: border-box;
            position: relative;
        }
    </style>
</head>
<body>

    <div class="fixed top-4 right-4 no-print flex gap-4">
        <button onclick="window.close()" class="px-4 py-2 bg-gray-500 text-white rounded shadow hover:bg-gray-600 transition">Close</button>
        <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white rounded shadow hover:bg-blue-700 transition font-semibold">🖨️ Print Label</button>
    </div>

    <div class="label-container flex flex-col justify-between">
        <!-- Company Header (Sender) -->
        <div class="flex justify-between items-start border-b-2 border-gray-200 pb-6">
            <div class="flex items-center gap-4">
                <!-- Company Logo -->
                <div class="flex items-center justify-center">
                    <img src="{{ asset('img/BRI_Logo.png') }}" alt="BRI Trading Logo" class="h-16 object-contain">
                </div>
                <div>
                    <h1 class="text-2xl font-black text-gray-800 tracking-tight uppercase">BRI Trading (Pvt) Ltd</h1>
                    <p class="text-gray-600 text-sm mt-1">NO:66/4/2 WEBADAGALLA,</p>
                    <p class="text-gray-600 text-sm">NITTAMBUWA.</p>
                    <p class="text-gray-600 text-sm">Phone: +94777800611 / +94701260560</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm font-bold text-gray-500 uppercase tracking-widest">Order Number</p>
                <p class="text-3xl font-black text-gray-900 mt-1">#{{ $order->reference }}</p>
                <p class="text-sm text-gray-500 mt-2">Date: {{ $order->created_at->format('M d, Y') }}</p>
                @if ($order->tracking_number)
                    <p class="text-sm font-bold text-gray-900 mt-1">Tracking: {{ $order->tracking_number }}</p>
                @endif
            </div>
        </div>

        <!-- Shipping Details (Recipient) -->
        <div class="flex-grow flex flex-col justify-center py-8">
            <div class="max-w-2xl">
                <p class="text-sm font-bold text-blue-600 uppercase tracking-widest mb-2">Ship To:</p>
                <h2 class="text-5xl font-black text-gray-900 leading-tight">{{ $order->customer->name }}</h2>
                
                <div class="mt-6 text-2xl text-gray-800 font-medium leading-snug">
                    {!! nl2br(e($order->customer->address)) !!}
                </div>
                
                <div class="mt-6 inline-flex items-center gap-3 bg-gray-100 px-4 py-2 rounded-lg border border-gray-200">
                    <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                    <span class="text-xl font-bold tracking-wider text-gray-800">{{ $order->customer->phone ?? 'N/A' }}</span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="pt-4 border-t-2 border-gray-200 flex justify-between items-center text-gray-500 text-sm">
            <p>Please handle with care.</p>
            <p>Weight: _________</p>
            <p>1 / 1</p>
        </div>
    </div>

</body>
</html>
