<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outstanding Payments Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; padding: 0; font-size: 24px; }
        .header p { margin: 5px 0; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f9f9f9; }
        .text-right { text-align: right; }
        .partial-payments { font-size: 11px; color: #666; padding-left: 10px; list-style-type: disc; margin: 4px 0 0 0; }
        .totals { margin-top: 20px; text-align: right; font-weight: bold; font-size: 16px; }
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
        <h1>Outstanding Payments Report</h1>
        <p>Generated on {{ now()->format('Y-m-d H:i') }}</p>
    </div>

    @php
        $totalOutstanding = 0;
        $totalPaid = 0;
        $totalAmount = 0;
    @endphp

    <table>
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Date</th>
                <th>Customer Details</th>
                <th class="text-right">Total Amount</th>
                <th class="text-right">Paid Amount</th>
                <th class="text-right">Balance Due</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $invoice)
                @php
                    $paid = $invoice->payments->where('status', '!=', 'bounced')->sum('amount');
                    $totalOutstanding += $invoice->balance_due;
                    $totalAmount += $invoice->total_amount;
                    $totalPaid += $paid;
                @endphp
                <tr>
                    <td>{{ $invoice->invoice_number }}</td>
                    <td>{{ \Carbon\Carbon::parse($invoice->issued_date)->format('Y-m-d') }}</td>
                    <td>
                        <strong>{{ $invoice->order?->customer?->name ?? 'N/A' }}</strong><br>
                        <small>{{ $invoice->order?->customer?->address }}</small>
                    </td>
                    <td class="text-right">{{ number_format($invoice->total_amount, 2) }}</td>
                    <td class="text-right">
                        {{ number_format($paid, 2) }}
                        @if($invoice->payments->count() > 0)
                            <ul class="partial-payments">
                                @foreach($invoice->payments as $payment)
                                    <li>{{ $payment->transaction_date->format('Y-m-d') }}: {{ number_format($payment->amount, 2) }} {{ $payment->method == 'cheque' ? '(Cheque: '.$payment->cheque_number.')' : '(Cash)' }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                    <td class="text-right" style="color: #e3342f; font-weight: bold;">{{ number_format($invoice->balance_due, 2) }}</td>
                </tr>
            @endforeach
            @if($invoices->isEmpty())
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px;">No outstanding payments found.</td>
                </tr>
            @endif
        </tbody>
        @if($invoices->isNotEmpty())
        <tfoot>
            <tr style="background-color: #f1f1f1; font-weight: bold;">
                <td colspan="3" class="text-right">GRAND TOTALS</td>
                <td class="text-right">{{ number_format($totalAmount, 2) }}</td>
                <td class="text-right">{{ number_format($totalPaid, 2) }}</td>
                <td class="text-right" style="color: #e3342f;">{{ number_format($totalOutstanding, 2) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>

</body>
</html>
