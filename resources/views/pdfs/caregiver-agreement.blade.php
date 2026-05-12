<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Caregiver Statement of Agreement</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; line-height: 1.6; }
        h1 { color: #1B3A5C; }
        .signature { margin-top: 50px; }
    </style>
</head>
<body>
    <h1>Caregiver Statement of Agreement</h1>
    
    <p>I understand that I am working as an independent contractor for Sitterwise, Inc. I am free to accept or reject any job offered. I will not provide childcare for families originally referred by Sitterwise "under the table" without notifying Sitterwise.</p>
    
    <p>I agree to maintain confidentiality regarding all client families and their personal information. I will not share client contact information or discuss client business with unauthorized parties.</p>
    
    <div class="signature">
        <p><strong>Typed Signature:</strong> {{ $data['agreement']['signature'] ?? 'N/A' }}</p>
        <p><strong>Date:</strong> {{ now()->format('m/d/Y') }}</p>
    </div>
</body>
</html>
