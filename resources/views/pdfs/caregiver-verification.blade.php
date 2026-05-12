<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Caregiver Statement of Verification</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; line-height: 1.6; }
        h1 { color: #1B3A5C; }
        .signature { margin-top: 50px; }
    </style>
</head>
<body>
    <h1>Caregiver Statement of Verification</h1>
    
    <p>I, {{ $caregiver->first_name }} {{ $caregiver->last_name }}, certify under penalty of perjury that the answers given herein are true and complete to the best of my knowledge.</p>
    
    <p>I authorize the investigation of all statements contained in this document, and I understand that this document is not intended to be a contract of employment.</p>
    
    <p>In the event that I am accepted as a Sitterwise caregiver, I understand that false or misleading information given on this document or in my interview may result in termination of our agreement.</p>
    
    <div class="signature">
        <p><strong>Typed Signature:</strong> {{ $data['verification']['signature'] ?? 'N/A' }}</p>
        <p><strong>Date:</strong> {{ now()->format('m/d/Y') }}</p>
    </div>
</body>
</html>
