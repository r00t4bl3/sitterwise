<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;

class ReferenceFinalReminderMail extends SendGridDynamicMail implements ShouldQueue
{
    public function __construct(
        public string $referenceName,
        public string $applicantName,
        public string $token,
    ) {}

    protected function templateId(): string
    {
        return 'd-5edba720ef7b4aec8a8b3d70a4dc2cbd';
    }

    protected function templateData(): array
    {
        return [
            'reference_name' => $this->referenceName,
            'applicant_name' => $this->applicantName,
            'reference_url' => route('references.show', $this->token),
        ];
    }

    protected function subjectLine(): string
    {
        return "Final reminder: Reference request for {$this->applicantName}";
    }

    protected function bladeView(): ?string
    {
        return 'emails.reference-final-reminder';
    }
}
