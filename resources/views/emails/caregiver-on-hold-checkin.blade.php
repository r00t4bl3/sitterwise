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
        <h1>Just checking in!</h1>
        <p>Your Sitterwise account is currently paused</p>
    </div>
    <div class="content">
        <p>Hi {{ $caregiverName }},</p>

        @if ($tier === 'final')
            <p>It's been <strong>{{ $daysOnHold }} days</strong> since you paused your account. If you don't resume within the next 30 days, your account will be automatically archived.</p>
            <p>Archiving means your status will be set to Inactive. Your profile, ratings, and history will be preserved, but you'll need to contact admin to become active again.</p>
        @elseif ($tier === 'reminder')
            <p>It's been <strong>{{ $daysOnHold }} days</strong> since you paused your account. Just a friendly reminder that you can resume anytime from your account settings.</p>
            <p>We'd love to have you back! Your profile, ratings, and history are all waiting.</p>
        @else
            <p>It's been <strong>{{ $daysOnHold }} days</strong> since you paused your account. Hope everything is going well on your end!</p>
            <p>Whenever you're ready to come back, you can resume right from your account settings — no admin needed.</p>
        @endif

        <p style="margin: 24px 0;">
            <a href="{{ config('app.url') }}/settings/caregiver/pause" style="display: inline-block; background-color: #F48A91; color: #fff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 500;">
                Manage Your Account
            </a>
        </p>
        <p style="font-size: 13px; color: #999;">
            Or copy this link: <a href="{{ config('app.url') }}/settings/caregiver/pause">{{ config('app.url') }}/settings/caregiver/pause</a>
        </p>
    </div>
    <div class="footer">
        <p>This is an automated notification from Sitterwise.</p>
        <p>Sitterwise — San Diego's most trusted childcare agency.</p>
    </div>
</body>
</html>
