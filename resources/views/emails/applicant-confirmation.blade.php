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
        <h1>Application Received!</h1>
        <p>Thank you for applying to join Sitterwise</p>
    </div>
    <div class="content">
        <p>Hi {{ $applicantName }},</p>
        <p>We've received your application to join the Sitterwise team. Here's what to expect next:</p>
        <ol>
            <li><strong>Reference Check</strong> — We'll reach out to your references over the coming days.</li>
            <li><strong>Review</strong> — Our team will review your application and references.</li>
            <li><strong>Next Steps</strong> — If you're a good fit, we'll reach out to schedule an interview.</li>
        </ol>
        <p>You can expect to hear from us within 3–5 business days.</p>
        <p style="margin: 24px 0;">
            <a clicktracking=off href="{{ $statusUrl }}" style="display: inline-block; background-color: #F48A91; color: #fff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 500;">
                Track Your Application Status
            </a>
        </p>
        <p style="font-size: 13px; color: #999;">
            Or copy this link into your browser: <a clicktracking=off href="{{ $statusUrl }}">{{ $statusUrl }}</a>
        </p>
        <p>If you have any questions in the meantime, please don't hesitate to reach out.</p>
    </div>
    <div class="footer">
        <p>This is an automated notification from Sitterwise.</p>
        <p>Sitterwise — San Diego's most trusted childcare agency.</p>
    </div>
</body>
</html>
