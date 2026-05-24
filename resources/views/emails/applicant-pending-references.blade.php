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
        <h1>References still pending</h1>
        <p>Your Sitterwise application is on hold</p>
    </div>
    <div class="content">
        <p>Hi {{ $applicantName }},</p>
        <p>We're still waiting for your references to complete their forms. Your application can't move forward until we hear back from them.</p>

@if ($daysSinceSubmission >= 7)
        <p>It's been over a week since you applied. Please reach out to your references and ask them to check their email for the reference request link.</p>
@else
        <p>If your references haven't received the request email, ask them to check their spam folder, or contact us and we can resend it.</p>
@endif

        <p>Thank you for your patience as we complete the review process.</p>
    </div>
    <div class="footer">
        <p>Sitterwise — San Diego's most trusted childcare agency.</p>
        <p>Questions? Contact us at {{ config('mail.from.address', 'admin@sitterwise.io') }}.</p>
    </div>
</body>
</html>
