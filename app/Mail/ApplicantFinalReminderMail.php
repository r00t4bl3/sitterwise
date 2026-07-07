<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;

class ApplicantFinalReminderMail extends SendGridDynamicMail implements ShouldQueue
{
    public function __construct(
        public string $email,
    ) {}

    protected function templateId(): string
    {
        return 'd-33fa38edec7f4b2cb39b78d2ab652c9f';
    }

    protected function templateData(): array
    {
        return [
            'apply_url' => url('/caregiver/apply'),
        ];
    }

    protected function subjectLine(): string
    {
        return 'Last chance — finish your Sitterwise application';
    }

    protected function bladeView(): ?string
    {
        return 'emails.final-reminder';
    }
}
