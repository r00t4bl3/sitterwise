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
        .checklist { list-style: none; padding: 0; margin: 16px 0; }
        .checklist li { padding: 8px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #4b5563; }
        .checklist li:last-child { border-bottom: none; }
        .footer { margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb; font-size: 14px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Welcome to the Team!</h1>
        <p>We're thrilled to have you join Sitterwise</p>
    </div>
    <div class="content">
        <p>Hi {{ $applicantName }},</p>
        <p>Congratulations — you've been hired! We're excited to welcome you to the Sitterwise team.</p>
        <p>Before you can start taking jobs, we need to complete a few onboarding items:</p>
        <ul class="checklist">
            <li>OnPay Setup</li>
            <li>Background Check</li>
            <li>CPR Uploaded</li>
            <li>Trustline Submitted</li>
            <li>Dress Code Acknowledged</li>
            <li>Training Quiz Passed</li>
        </ul>
        <p>Your admin will guide you through each step and mark items as completed.</p>
        <p style="margin: 24px 0;">
            <a href="{{ $statusUrl }}" style="display: inline-block; background-color: #F48A91; color: #fff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 500;">
                View Your Onboarding Status
            </a>
        </p>
        <p style="font-size: 13px; color: #999;">
            Or copy this link into your browser: <a href="{{ $statusUrl }}">{{ $statusUrl }}</a>
        </p>
        <p>If you have any questions, please reach out to your admin contact.</p>
    </div>
    <div class="footer">
        <p>Sitterwise — San Diego's most trusted childcare agency.</p>
    </div>
</body>
</html>
