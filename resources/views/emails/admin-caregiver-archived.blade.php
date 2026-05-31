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
        .detail-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f3f4f6; }
        .detail-row:last-child { border-bottom: none; }
        .label { font-weight: 600; color: #666; }
        .value { color: #1a1a1a; }
        .btn { display: inline-block; background-color: #F48A91; color: #fff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 500; }
        .footer { margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb; font-size: 14px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Caregiver Archived</h1>
        <p>Automatic archive notification</p>
    </div>
    <div class="content">
        <p><strong>{{ $caregiverName }}</strong> has been automatically archived after being on hold for <strong>{{ $daysOnHold }} days</strong>.</p>
        <p>Their status has been changed from "On Hold" to "Inactive" and their pause record has been closed.</p>
        <div class="detail-row">
            <span class="label">Caregiver</span>
            <span class="value">{{ $caregiverName }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Days on Hold</span>
            <span class="value">{{ $daysOnHold }}</span>
        </div>
        <div class="detail-row">
            <span class="label">New Status</span>
            <span class="value">Inactive</span>
        </div>
        <p style="margin: 24px 0;">
            <a href="{{ config('app.url') }}/caregivers/{{ $caregiverId }}" class="btn">
                View Caregiver Profile
            </a>
        </p>
    </div>
    <div class="footer">
        <p>This is an automated notification from Sitterwise.</p>
        <p>Sitterwise — San Diego's most trusted childcare agency.</p>
    </div>
</body>
</html>
