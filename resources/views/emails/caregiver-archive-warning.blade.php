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
    </style>
</head>
<body>
    <div class="header">
        <h1>Account will be archived soon</h1>
        <p>Your Sitterwise account has been paused for {{ $daysOnHold }} days</p>
    </div>
    <div class="content">
        <p>Hi {{ $caregiverName }},</p>
        <p>Your account has been paused for <strong>{{ $daysOnHold }} days</strong>. In 14 days, your account will be automatically set to Inactive.</p>
        <p>What this means:</p>
        <ul>
            <li>Your profile, ratings, and job history will be preserved</li>
            <li>You won't receive job offers until an admin reactivates you</li>
            <li>Reactivating requires contacting the Sitterwise team</li>
        </ul>
        <p>To avoid this, simply resume your account now:</p>
        <p style="margin: 24px 0;">
            <a clicktracking=off href="{{ config('app.url') }}/settings/caregiver/pause" style="display: inline-block; background-color: #F48A91; color: #fff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 500;">
                Resume My Account
            </a>
        </p>
        <p style="font-size: 13px; color: #999;">
            Or copy this link: <a clicktracking=off href="{{ config('app.url') }}/settings/caregiver/pause">{{ config('app.url') }}/settings/caregiver/pause</a>
        </p>
    </div>
    <div class="footer">
        <p>This is an automated notification from Sitterwise.</p>
        <p>Sitterwise — San Diego's most trusted childcare agency.</p>
    </div>
</body>
</html>
