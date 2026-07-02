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
            background: #f8fafc;
            padding: 24px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .header h1 {
            margin: 0 0 8px 0;
            font-size: 24px;
            color: #1a1a1a;
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
        .cta-button {
            display: inline-block;
            background: #F48A91;
            color: #fff !important;
            padding: 14px 32px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
            margin: 24px 0;
        }
        .cta-button:hover {
            background: #2563eb;
        }
        .footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #666;
        }
        .warning {
            background: #fef3c7;
            border: 1px solid #fde68a;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 24px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>New Booking Available</h1>
        <p>A new booking is available!</p>
    </div>

    <div class="warning">
        <strong>⏱️ First Come, First Served:</strong> Be the first to accept this booking to secure it. Once you accept, you'll have 60 seconds to confirm.
    </div>

    <div class="details">
        <div class="detail-row">
            <span class="label">Client</span>
            <span class="value">{{ $booking->client_first_name ?? $booking->client->first_name }} {{ $booking->client_last_name ?? $booking->client->last_name }}</span>
        </div>
        @if(($booking->client_phone ?? $booking->client->user?->phone))
        <div class="detail-row">
            <span class="label">Phone</span>
            <span class="value">{{ $booking->client_phone ?? $booking->client->user?->phone }}</span>
        </div>
        @endif
        <div class="detail-row">
            <span class="label">Start Time</span>
            <span class="value">{{ $booking->start_datetime->setTimezone('America/Los_Angeles')->format('M j, Y g:i A') }}</span>
        </div>
        <div class="detail-row">
            <span class="label">End Time</span>
            <span class="value">{{ $booking->end_datetime->setTimezone('America/Los_Angeles')->format('M j, Y g:i A') }}</span>
        </div>
        @if($booking->address_line1)
        <div class="detail-row">
            <span class="label">Location</span>
            <span class="value">
                {{ $booking->address_line1 }}
                @if($booking->address_city), {{ $booking->address_city }} @endif
            </span>
        </div>
        @endif
    </div>

    <div style="text-align: center;">
        <a clicktracking=off href="{{ route('jobs.short', $booking) }}" class="cta-button">
            View & Accept Booking
        </a>
    </div>

    <div class="footer">
        <p>This booking was sent by Sitterwise. You're receiving this email because you've been shortlisted for this booking.</p>
        <p>This is an automated notification from Sitterwise.</p>
        <p>Sitterwise — San Diego's most trusted childcare agency.</p>
    </div>
</body>
</html>
