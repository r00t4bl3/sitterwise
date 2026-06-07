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
        .dates-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        .dates-table th {
            text-align: left;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e5e7eb;
        }
        .dates-table td {
            padding: 10px 12px;
            color: #1a1a1a;
            border-bottom: 1px solid #f3f4f6;
        }
        .dates-table tr:last-child td {
            border-bottom: none;
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
        <h1>New Multi-Day Booking Created</h1>
        <p>A new group booking has been submitted.</p>
    </div>

    <div class="details">
        <div class="detail-row">
            <span class="label">Client</span>
            <span class="value">{{ $bookingGroup->client_first_name }} {{ $bookingGroup->client_last_name }}</span>
        </div>
        @if($bookingGroup->client_email)
        <div class="detail-row">
            <span class="label">Email</span>
            <span class="value">{{ $bookingGroup->client_email }}</span>
        </div>
        @endif
        @if($bookingGroup->client_phone)
        <div class="detail-row">
            <span class="label">Phone</span>
            <span class="value">{{ $bookingGroup->client_phone }}</span>
        </div>
        @endif
        <div class="detail-row">
            <span class="label">Booking #</span>
            <span class="value">{{ $bookingGroup->bookings->first()->ulid }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Service Type</span>
            <span class="value">{{ ucfirst($bookingGroup->service_type) }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Location</span>
            <span class="value">
                {{ $bookingGroup->hotel_name ?? $bookingGroup->hotel?->name ?? $bookingGroup->address_line1 }}
                @if($bookingGroup->address_city), {{ $bookingGroup->address_city }}@endif
            </span>
        </div>
    </div>

    <div class="details">
        <h3 style="margin: 0 0 12px 0; font-size: 16px; color: #1a1a1a;">Dates ({{ $bookingGroup->bookings->count() }})</h3>
        <table class="dates-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bookingGroup->bookings as $booking)
                    <tr>
                        <td>{{ $booking->start_datetime->setTimezone('America/Los_Angeles')->format('l, F j, Y') }}</td>
                        <td>{{ $booking->start_datetime->setTimezone('America/Los_Angeles')->format('g:i A') }} - {{ $booking->end_datetime->setTimezone('America/Los_Angeles')->format('g:i A') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>Please review this booking and notify available caregivers.</p>
        <p>This is an automated notification from Sitterwise.</p>
        <p>Sitterwise — San Diego's most trusted childcare agency.</p>
    </div>
</body>
</html>
