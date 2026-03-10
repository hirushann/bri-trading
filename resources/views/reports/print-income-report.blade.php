<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; padding: 0; font-size: 24px; }
        .header p { margin: 5px 0; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f9f9f9; }
        .text-right { text-align: right; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef08a; color: #854d0e; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #000; color: #fff; border: none; cursor: pointer;">Print Report</button>
    </div>

    <div class="header">
        <h1>Income Report</h1>
        <p>Generated on {{ now()->format('Y-m-d H:i') }}</p>
    </div>

    @php
        $totalAmount = 0;
    @endphp

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Invoice #</th>
                <th>Customer Name</th>
                <th>Method</th>
                <th>Cheque Details</th>
                <th class="text-right">Amount (LKR)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
                @php
                    $totalAmount += $payment->amount;
                @endphp
                <tr>
                    <td>{{ \Carbon\Carbon::parse($payment->transaction_date)->format('Y-m-d') }}</td>
                    <td>{{ $payment->invoice?->invoice_number ?? 'N/A' }}</td>
                    <td>{{ $payment->invoice?->order?->customer?->name ?? 'N/A' }}</td>
                    <td>
                        <span class="badge {{ $payment->method == 'cash' ? 'badge-success' : 'badge-warning' }}">
                            {{ ucfirst($payment->method) }}
                        </span>
                    </td>
                    <td>
                        @if($payment->method == 'cheque')
                            No: {{ $payment->cheque_number }}<br>
                            Date: {{ $payment->cheque_date ? \Carbon\Carbon::parse($payment->cheque_date)->format('Y-m-d') : '' }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($payment->amount, 2) }}</td>
                </tr>
            @endforeach
            @if($payments->isEmpty())
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px;">No income records found for the selected dates.</td>
                </tr>
            @endif
        </tbody>
        @if($payments->isNotEmpty())
        <tfoot>
            <tr style="background-color: #f1f1f1; font-weight: bold;">
                <td colspan="5" class="text-right">TOTAL INCOME</td>
                <td class="text-right">{{ number_format($totalAmount, 2) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>

</body>
</html>
