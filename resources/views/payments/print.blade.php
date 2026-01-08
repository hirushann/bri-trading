<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt {{ $payment->id }}</title>
    <style>
        body { font-family: sans-serif; padding: 40px; }
        .receipt-container { max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; }
        .header { text-align: center; border-bottom: 2px dashed #ddd; padding-bottom: 20px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #333; }
        .details { margin-bottom: 20px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .footer { text-align: center; font-size: 12px; color: #666; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 10px; }
        @media print {
            body { padding: 0; }
            button { display: none; }
            .receipt-container { border: none; }
        }
        .payment-company-details{
            display: flex;
            gap: 25px;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        .payment-company-details p, .payment-company-details h2{
            margin: 0px;
        }
        .payment-company-details .company-names{
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: start;
            justify-content: flex-start;
            margin: 10px 0px;
        }
    </style>
</head>
<body onload="window.print()">
    <div class="receipt-container">
        <div class="header">
            
            <div class="payment-company-details">
                <div>
                    <img src="{{ asset('img/BRI_Logo.png') }}" alt="BRI Trading Logo" width="100">
                </div>
                <div class="company-names">
                    <h2>BRI Trading (Pvt) Ltd</h2>
                    <p>NO:66/4/2 WEBADAGALlA,</p>
                    <p>NITTAMBUWA.</p>
                </div>
            </div>
            <h2>PAYMENT RECEIPT</h2>
        </div>

        <div class="details">
            <div class="row">
                <strong>Receipt No:</strong>
                <span>RC-{{ str_pad($payment->id, 5, '0', STR_PAD_LEFT) }}</span>
            </div>
            <div class="row">
                <strong>Date:</strong>
                <span>{{ $payment->transaction_date->format('Y-m-d') }}</span>
            </div>
            <div class="row">
                <strong>Customer:</strong>
                <span>{{ $payment->invoice->order->customer->name }}</span>
            </div>
            <div class="row">
                <strong>Invoice Ref:</strong>
                <span>{{ $payment->invoice->invoice_number }}</span>
            </div>
        </div>

        <div style="border-top: 2px solid #333; border-bottom: 2px solid #333; padding: 10px 0; margin-bottom: 20px;">
             <div class="row" style="font-size: 1.2em; font-weight: bold;">
                <strong>Amount Paid:</strong>
                <span>LKR {{ number_format($payment->amount, 2) }}</span>
            </div>
        </div>

        <div class="details">
            <div class="row">
                <strong>Payment Method:</strong>
                <span style="text-transform: capitalize">{{ $payment->method }}</span>
            </div>
            @if($payment->method === 'cheque')
            <div class="row">
                <strong>Cheque No:</strong>
                <span>{{ $payment->cheque_number }}</span>
            </div>
             <div class="row">
                <strong>Cheque Date:</strong>
                <span>{{ $payment->cheque_date?->format('Y-m-d') }}</span>
            </div>
            <div class="row">
                <strong>Bank:</strong>
                <span>{{ $payment->bank_name }} ({{ $payment->branch }})</span>
            </div>
            @endif
        </div>

        <div class="footer">
            <p>Thank you for your business!</p>
            <p>Authorized Signature</p>
        </div>
    </div>
</body>
</html>
