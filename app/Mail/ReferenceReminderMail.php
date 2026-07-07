<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;

class ReferenceReminderMail extends SendGridDynamicMail implements ShouldQueue
{
    public function __construct(
        public string $referenceName,
        public string $applicantName,
        public string $token,
    ) {}

    protected function templateId(): string
    {
        return 'd-0ca264e3ff9140f5be97765b372f6846';
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
        return "Reminder: Reference request for {$this->applicantName}";
    }

    protected function bladeView(): ?string
    {
        return 'emails.reference-reminder';
    }
}
