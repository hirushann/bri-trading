@extends('layouts.print')

@section('title', 'Commission Receipt #' . $commission->id)

@section('content')
    <div class="header">
        <h1>Commission Receipt</h1>
        <p>Receipt #: <strong>CR-{{ str_pad($commission->id, 6, '0', STR_PAD_LEFT) }}</strong></p>
        <p>Date: {{ $commission->paid_at?->format('d M Y') ?? 'N/A' }}</p>
    </div>

    <div class="billing-info">
        <div class="to">
            <h3>Sales Representative</h3>
            <p><strong>{{ $commission->user->name }}</strong></p>
            <p>{{ $commission->user->email }}</p>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    Commission Payment
                    @if($commission->payment)
                        <br><small>Ref: Payment #{{ $commission->payment->id }} (Invoice: {{ $commission->payment->invoice->invoice_number ?? 'N/A' }})</small>
                    @endif
                </td>
                <td class="text-right">{{ number_format($commission->amount, 2) }} LKR</td>
            </tr>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td class="text-right">Total Paid</td>
                <td class="text-right">{{ number_format($commission->amount, 2) }} LKR</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>Thank you for your hard work!</p>
        <div class="signature">
            <p>Authorized Signature</p>
        </div>
    </div>
@endsection
