<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8fafc; padding: 24px; border-radius: 8px; margin-bottom: 24px; }
        .header h1 { margin: 0 0 8px 0; font-size: 24px; color: #1a1a1a; }
        .header p { margin: 0; color: #666; }
        .content { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; margin-bottom: 24px; }
        .footer { margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb; font-size: 14px; color: #666; }
        .btn { display: inline-block; background-color: #F48A91; color: #fff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 500; }
        .details { background: #f8fafc; padding: 12px; border-radius: 6px; border-left: 3px solid #F48A91; margin: 16px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Booking Cancelled</h1>
        <p>Job #{{ $booking->id }} — Admin Action</p>
    </div>
    <div class="content">
        <p>Booking <strong>#{{ $booking->id }}</strong> has been cancelled by <strong>{{ $cancelledBy->name }}</strong>.</p>
        <div class="details">
            <p><strong>Client:</strong> {{ $booking->client?->first_name ?? 'N/A' }} {{ $booking->client?->last_name ?? '' }}</p>
            <p><strong>Service:</strong> {{ $booking->service_type_label }}</p>
            <p><strong>Date:</strong> {{ $booking->start_datetime?->setTimezone('America/Los_Angeles')->format('l, F j, Y') }} at {{ $booking->start_datetime?->setTimezone('America/Los_Angeles')->format('g:i A') }}</p>
            @if ($booking->caregiver)
                <p><strong>Caregiver:</strong> {{ $booking->caregiver->first_name }} {{ $booking->caregiver->last_name }}</p>
            @endif
            @if ($reason)
                <p><strong>Reason:</strong> {{ $reason }}</p>
            @endif
        </div>
        <p style="margin: 24px 0;">
            <a href="{{ config('app.url') }}/bookings/{{ $booking->id }}" class="btn">
                View Booking Details
            </a>
        </p>
    </div>
    <div class="footer">
        <p>This is an automated notification from Sitterwise.</p>
        <p>Sitterwise — San Diego's most trusted childcare agency.</p>
    </div>
</body>
</html>
