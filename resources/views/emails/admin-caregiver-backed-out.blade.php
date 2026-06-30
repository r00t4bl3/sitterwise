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
    </style>
</head>
<body>
    <div class="header">
        <h1>Caregiver Backed Out</h1>
        <p>Job #{{ $bookingId }}</p>
    </div>
    <div class="content">
        <p><strong>{{ $caregiverName }}</strong> has backed out of job <strong>#{{ $bookingId }}</strong>.</p>
        <p><strong>Reason given:</strong></p>
        <p style="background: #f8fafc; padding: 12px; border-radius: 6px; border-left: 3px solid #EF4444;">
            {{ $reason }}
        </p>
        <p>From Job History you can excuse the back-out, log a no-show, or log a late arrival:</p>
        <p style="margin: 24px 0;">
            <a clicktracking=off href="{{ config('app.url') }}/caregivers/{{ $caregiverId }}/jobs" class="btn">
                View Job Details
            </a>
        </p>
    </div>
    <div class="footer">
        <p>This is an automated notification from Sitterwise.</p>
        <p>Sitterwise — San Diego's most trusted childcare agency.</p>
    </div>
</body>
</html>
