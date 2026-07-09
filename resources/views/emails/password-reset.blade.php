<div>
    <p>Hi {{ $firstName }},</p>

    <p>We received a request to reset your Sitterwise password. Click the button
        below to choose a new one. This link will expire shortly.</p>

    <p>
        <a href="{{ $resetUrl }}">Reset your password</a>
    </p>

    <p>If you didn't request this, you can safely ignore this email — your
        password won't change.</p>
</div>
