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
        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 24px;
            font-size: 14px;
            color: #991b1b;
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
        <h1>Payment Failed</h1>
        <p>A payment attempt has failed for a booking</p>
    </div>

    <div class="error-box">
        <strong>Error:</strong> {{ $errorMessage }}
    </div>

    <div class="details">
        <div class="detail-row">
            <span class="label">Booking ID</span>
            <span class="value">#{{ $booking->id }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Client</span>
            <span class="value">{{ $booking->client?->first_name ?? $booking->client_first_name }} {{ $booking->client?->last_name ?? $booking->client_last_name }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Service</span>
            <span class="value">{{ $booking->service_type }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Date</span>
            <span class="value">{{ $booking->start_datetime?->format('M j, Y') ?? '—' }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Attempt</span>
            <span class="value">{{ $attemptCount }}/4</span>
        </div>
        <div class="detail-row">
            <span class="label">Total Amount</span>
            <span class="value">${{ number_format($booking->total_amount ?? 0, 2) }}</span>
        </div>
    </div>

    <div class="footer">
        <p>This is an automated notification from Sitterwise.</p>
    </div>
</body>
</html>
