<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: sans-serif; padding: 40px; }
        .invoice-header { display: flex; justify-content: space-between; margin-bottom: 40px; }
        .company-details h1 { margin: 0; color: #333; }
        .company-details p { margin: 5px 0; color: #666; }
        .invoice-details { text-align: right; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background-color: #f8f9fa; }
        .totals { float: right; width: 300px; }
        .totals-row { display: flex; justify-content: space-between; padding: 10px 0; }
        .totals-row.final { font-weight: bold; border-top: 2px solid #333; }
        @media print {
            body { padding: 0; }
            button { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="invoice-header">
        <div class="company-details">
            <h1>BRI Trading</h1>
            <p>Wholesale Vehicle Spare Parts</p>
            <p>123 Main Street, Colombo</p>
            <p>Email: admin@bri.com</p>
        </div>
        <div class="invoice-details">
            <h2>INVOICE</h2>
            <p><strong>#{{ $invoice->invoice_number }}</strong></p>
            <p>Date: {{ $invoice->issued_date }}</p>
            <p>Status: {{ ucfirst($invoice->status) }}</p>
        </div>
    </div>

    <div style="margin-bottom: 30px;">
        <strong>Bill To:</strong><br>
        {{ $invoice->order->customer->name }}<br>
        {{ $invoice->order->customer->address ?? 'No Address' }}<br>
        {{ $invoice->order->customer->phone ?? '' }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th style="text-align: right">Quantity</th>
                <th style="text-align: right">Unit Price</th>
                <th style="text-align: right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->order->items as $item)
            <tr>
                <td>{{ $item->product->name }}</td>
                <td style="text-align: right">{{ $item->quantity }}</td>
                <td style="text-align: right">{{ number_format($item->unit_price, 2) }}</td>
                <td style="text-align: right">{{ number_format($item->subtotal, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="totals-row final">
            <span>Total Amount:</span>
            <span>LKR {{ number_format($invoice->total_amount, 2) }}</span>
        </div>
        <div class="totals-row">
            <span>Paid:</span>
            <span>({{ number_format($invoice->payments->sum('amount'), 2) }})</span>
        </div>
        <div class="totals-row">
            <span>Balance Due:</span>
            <span>LKR {{ number_format($invoice->balance_due, 2) }}</span>
        </div>
    </div>
</body>
</html>
