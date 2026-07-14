<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #fef2f2;
            padding: 24px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .header h1 {
            margin: 0 0 8px 0;
            font-size: 24px;
            color: #991b1b;
        }
        .header p {
            margin: 0;
            color: #666;
        }
        .details {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .label {
            font-weight: 600;
            color: #666;
        }
        .value {
            color: #1a1a1a;
        }
        .reason-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 24px;
            font-size: 14px;
            color: #991b1b;
        }
        .cta {
            display: inline-block;
            background: #991b1b;
            color: #fff;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
        }
        .footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Payment didn't go through</h1>
        <p>Hi {{ $client_first_name }}, we couldn't process the payment for your recent booking.</p>
    </div>

    <p>We'll re-run the payment automatically, but you can help it go through faster by updating your payment method.</p>

    @if (! empty($decline_reason))
        <div class="reason-box">
            <strong>Reason:</strong> {{ $decline_reason }}
        </div>
    @endif

    <div class="details">
        <div class="detail-row">
            <span class="label">Service</span>
            <span class="value">{{ $service_type }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Date</span>
            <span class="value">{{ $service_date }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Amount Due</span>
            <span class="value">${{ $total_amount }}</span>
        </div>
        @if (! empty($booking_id))
            <div class="detail-row">
                <span class="label">Booking</span>
                <span class="value">#{{ $booking_id }}</span>
            </div>
        @endif
    </div>

    <p>
        <a href="{{ $update_payment_url }}" class="cta">Update payment method</a>
    </p>

    <div class="footer">
        <p>If you've already updated your card, you can ignore this message.</p>
        <p>Sitterwise — San Diego's most trusted childcare agency.</p>
    </div>
</body>
</html>
