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
        .cta-button { display: inline-block; background: #F48A91; color: #fff !important; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 16px; text-align: center; margin: 24px 0; }
        .footer { margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb; font-size: 14px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reminder: Reference Request</h1>
        <p>We're still waiting for your feedback</p>
    </div>
    <div class="content">
        <p>Dear {{ $referenceName }},</p>
        <p>This is a friendly reminder that <strong>{{ $applicantName }}</strong> has listed you as a reference as part of their application to join Sitterwise.</p>
        <p>Your feedback is an important part of our review process. The form takes approximately 5–10 minutes to complete.</p>
        <div style="text-align: center;">
            <a href="{{ config('app.url') }}/references/{{ $token }}" class="cta-button">
                Complete Reference
            </a>
        </div>
        <p style="font-size: 14px; color: #999;">If the button above doesn't work, copy and paste this link into your browser: {{ config('app.url') }}/references/{{ $token }}</p>
    </div>
    <div class="footer">
        <p>This reference request was sent on behalf of Sitterwise. If you have any questions, please contact us at {{ config('mail.from.address', 'admin@sitterwise.io') }}.</p>
    </div>
</body>
</html>
