<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - {{ $payment->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #10b981;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #10b981;
            margin-bottom: 10px;
        }
        .receipt-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .receipt-info div {
            flex: 1;
        }
        .receipt-info h3 {
            margin-top: 0;
            color: #10b981;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 5px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th,
        .items-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .items-table th {
            background-color: #f9fafb;
            font-weight: bold;
            color: #374151;
        }
        .total-section {
            text-align: right;
            margin-bottom: 30px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }
        .total-row.final {
            font-weight: bold;
            font-size: 18px;
            border-top: 2px solid #10b981;
            padding-top: 12px;
            margin-top: 12px;
        }
        .footer {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status.succeeded {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status.refunded {
            background-color: #dbeafe;
            color: #1e40af;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">Comic Platform</div>
        <h1>Payment Receipt</h1>
        <p>Receipt #{{ str_pad($payment->id, 8, '0', STR_PAD_LEFT) }}</p>
    </div>

    <div class="receipt-info">
        <div>
            <h3>Bill To</h3>
            <p>
                <strong>{{ $payment->user->name }}</strong><br>
                {{ $payment->user->email }}
            </p>
        </div>
        <div>
            <h3>Payment Details</h3>
            <p>
                <strong>Date:</strong> {{ $payment->paid_at?->format('F j, Y') ?? 'N/A' }}<br>
                <strong>Payment ID:</strong> {{ $payment->id }}<br>
                <strong>Status:</strong> 
                <span class="status {{ $payment->status }}">{{ ucfirst($payment->status) }}</span><br>
                <strong>Method:</strong> {{ $payment->payment_type_display }}
            </p>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Type</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    @if($payment->comic)
                        <strong>{{ $payment->comic->title }}</strong>
                        @if($payment->comic->author)
                            <br><small>by {{ $payment->comic->author }}</small>
                        @endif
                    @else
                        {{ $payment->payment_type_display }}
                    @endif
                </td>
                <td>{{ $payment->payment_type_display }}</td>
                <td>${{ number_format($payment->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-row">
            <span>Subtotal:</span>
            <span>${{ number_format($payment->amount, 2) }}</span>
        </div>
        <div class="total-row">
            <span>Tax:</span>
            <span>$0.00</span>
        </div>
        @if($payment->refund_amount > 0)
            <div class="total-row">
                <span>Refunded:</span>
                <span>-${{ number_format($payment->refund_amount, 2) }}</span>
            </div>
        @endif
        <div class="total-row final">
            <span>Total {{ $payment->refund_amount > 0 ? 'Charged' : 'Paid' }}:</span>
            <span>${{ number_format($payment->amount - ($payment->refund_amount ?? 0), 2) }}</span>
        </div>
    </div>

    <div class="footer">
        <p>Thank you for your purchase!</p>
        <p>
            This is an electronic receipt for your records.<br>
            For support, please contact us at support@comicplatform.com
        </p>
        <p>
            <small>
                Generated on {{ now()->format('F j, Y \a\t g:i A') }}<br>
                Transaction processed securely via Stripe
            </small>
        </p>
    </div>
</body>
</html>