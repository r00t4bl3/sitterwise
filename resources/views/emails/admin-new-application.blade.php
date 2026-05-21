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
        <h1>New Caregiver Application</h1>
        <p>A new application has been submitted</p>
    </div>
    <div class="content">
        <p><strong>{{ $applicantName }}</strong> has submitted a caregiver application.</p>
        <p><strong>Email:</strong> {{ $applicantEmail }}</p>
        <p>Please log in to the admin dashboard to review the application and manage next steps.</p>
        <div style="text-align: center; margin: 24px 0;">
            <a href="{{ config('app.url') }}/admin/caregivers" style="display: inline-block; background: #3b82f6; color: #fff !important; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 16px;">
                Review Application
            </a>
        </div>
    </div>
    <div class="footer">
        <p>This is an automated notification from Sitterwise.</p>
    </div>
</body>
</html>
