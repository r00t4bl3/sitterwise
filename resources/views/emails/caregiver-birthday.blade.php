<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #FDF0F1; padding: 28px 24px; border-radius: 8px; margin-bottom: 24px; text-align: center; }
        .header h1 { margin: 0 0 8px 0; font-size: 26px; color: #1a1a1a; }
        .header p { margin: 0; color: #666; }
        .content { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; margin-bottom: 24px; }
        .footer { margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb; font-size: 14px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Happy Birthday, {{ $caregiverFirstName }}!</h1>
        <p>From all of us at Sitterwise</p>
    </div>
    <div class="content">
        <p>Hi {{ $caregiverFirstName }},</p>
        <p>Wishing you a wonderful birthday! Thank you for everything you do for the families you care for &mdash; you make a real difference, and we're so glad to have you on the Sitterwise team.</p>
        <p>We hope your day is filled with joy, relaxation, and maybe a slice (or two) of cake.</p>
        <p>Warmly,<br>The Sitterwise Team</p>
    </div>
    <div class="footer">
        <p>This is an automated birthday greeting from Sitterwise.</p>
        <p>Sitterwise &mdash; San Diego's most trusted childcare agency.</p>
    </div>
</body>
</html>
