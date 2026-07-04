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
        .section-title { font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin: 20px 0 8px; }
        table.ratings { width: 100%; border-collapse: collapse; }
        table.ratings td { padding: 6px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        table.ratings td.val { text-align: right; font-weight: 600; color: #1a1a1a; white-space: nowrap; }
        .text-block { white-space: pre-wrap; font-size: 14px; margin: 0 0 8px; }
        .muted { color: #9ca3af; }
        .btn { display: inline-block; background: #2F6B52; color: #fff !important; text-decoration: none; padding: 10px 18px; border-radius: 6px; font-size: 14px; font-weight: 600; margin-top: 8px; }
        .footer { margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb; font-size: 14px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reference Completed</h1>
        <p>{{ $reference->reference_name }} submitted a reference for {{ $applicantName }}</p>
    </div>

    <div class="content">
        @php
            $ratings = [
                'Overall recommendation' => $reference->rating_overall_recommendation,
                'Reliability' => $reference->rating_reliability,
                'Trustworthiness' => $reference->rating_trustworthiness,
                'Maturity' => $reference->rating_maturity,
                'Communication' => $reference->rating_communication,
                'Warmth' => $reference->rating_warmth,
                'Appearance' => $reference->rating_appearance,
                'Punctuality' => $reference->rating_punctuality,
            ];
            $ratings = array_filter($ratings, fn ($v) => ! is_null($v));
        @endphp

        @if ($reference->relationship || $reference->years_known)
            <p class="muted" style="margin-top:0;font-size:14px;">
                {{ $reference->relationship }}@if ($reference->relationship && $reference->years_known) &middot; @endif
                @if ($reference->years_known) known {{ $reference->years_known }} @endif
            </p>
        @endif

        @if (count($ratings))
            <div class="section-title">Ratings</div>
            <table class="ratings">
                @foreach ($ratings as $label => $value)
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="val">{{ $value }}/5</td>
                    </tr>
                @endforeach
            </table>
        @endif

        @if ($reference->strengths)
            <div class="section-title">Strengths</div>
            <p class="text-block">{{ $reference->strengths }}</p>
        @endif

        @if ($reference->concerns)
            <div class="section-title">Concerns</div>
            <p class="text-block">{{ $reference->concerns }}</p>
        @endif

        @if ($reference->additional_comments)
            <div class="section-title">Additional comments</div>
            <p class="text-block">{{ $reference->additional_comments }}</p>
        @endif

        @if ($reviewUrl)
            <p style="margin-top:20px;">
                <a href="{{ $reviewUrl }}" class="btn">View full application</a>
            </p>
        @endif
    </div>

    <div class="footer">
        <p>This is an automated notification from Sitterwise.</p>
        <p>Sitterwise — San Diego's most trusted childcare agency.</p>
    </div>
</body>
</html>
