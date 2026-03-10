<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 14px; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; padding: 0; font-size: 24px; }
        .header p { margin: 5px 0; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f9f9f9; }
        .text-right { text-align: right; }
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
        <h1>Expense Report</h1>
        <p>Generated on {{ now()->format('Y-m-d H:i') }}</p>
    </div>

    @php
        $totalAmount = 0;
    @endphp

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Description</th>
                <th>Recorded By</th>
                <th class="text-right">Amount (LKR)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($expenses as $expense)
                @php
                    $totalAmount += $expense->amount;
                @endphp
                <tr>
                    <td>{{ \Carbon\Carbon::parse($expense->date)->format('Y-m-d') }}</td>
                    <td>{{ $expense->category?->name ?? 'N/A' }}</td>
                    <td>{{ $expense->description }}</td>
                    <td>{{ $expense->user?->name ?? 'N/A' }}</td>
                    <td class="text-right">{{ number_format($expense->amount, 2) }}</td>
                </tr>
            @endforeach
            @if($expenses->isEmpty())
                <tr>
                    <td colspan="5" style="text-align: center; padding: 20px;">No expense records found.</td>
                </tr>
            @endif
        </tbody>
        @if($expenses->isNotEmpty())
        <tfoot>
            <tr style="background-color: #f1f1f1; font-weight: bold;">
                <td colspan="4" class="text-right">TOTAL EXPENSES</td>
                <td class="text-right">{{ number_format($totalAmount, 2) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>

</body>
</html>
