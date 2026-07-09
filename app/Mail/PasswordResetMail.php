<?php

namespace App\Mail;

class PasswordResetMail extends SendGridDynamicMail
{
    public function __construct(
        public string $firstName,
        public string $resetUrl,
    ) {}

    protected function templateId(): string
    {
        return 'd-ed180932c2904c028fc5df6bd90a0c69';
    }

    protected function templateData(): array
    {
        return [
            'first_name' => $this->firstName,
            'reset_url' => $this->resetUrl,
        ];
    }

    protected function subjectLine(): string
    {
        return 'Reset your Sitterwise password';
    }

    protected function bladeView(): ?string
    {
        return 'emails.password-reset';
    }

    protected function shouldBccTeam(): bool
    {
        // Never BCC a password reset — it carries a single-use reset link.
        return false;
    }
}
