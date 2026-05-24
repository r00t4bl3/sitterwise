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
        .cta-button { display: inline-block; background: #3b82f6; color: #fff !important; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 16px; text-align: center; margin: 24px 0; }
        .footer { margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb; font-size: 14px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>You're almost there!</h1>
        <p>Finish your Sitterwise application</p>
    </div>
    <div class="content">
        <p>Hi there,</p>
        <p>We noticed you started an application to join Sitterwise but haven't finished yet. We'd love to see your completed application!</p>
        <p>Most applications take about 10–15 minutes to complete. You can pick up right where you left off.</p>
        <div style="text-align: center;">
            <a href="{{ config('app.url') }}/caregiver/apply" class="cta-button">
                Resume Application
            </a>
        </div>
        <p style="font-size: 14px; color: #999;">If the button above doesn't work, copy and paste this link into your browser: {{ config('app.url') }}/caregiver/apply</p>
    </div>
    <div class="footer">
        <p>Sitterwise — San Diego's most trusted childcare agency.</p>
    </div>
</body>
</html>
