<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;

class ApplicantResumeApplicationMail extends SendGridDynamicMail implements ShouldQueue
{
    public function __construct(
        public string $email,
    ) {}

    protected function templateId(): string
    {
        return 'd-4cf619b4ce1e4b62b1508de56f6a1069';
    }

    protected function templateData(): array
    {
        return [
            'apply_url' => url('/caregiver/apply'),
        ];
    }

    protected function subjectLine(): string
    {
        return 'Come back to your Sitterwise application';
    }

    protected function bladeView(): ?string
    {
        return 'emails.resume-application';
    }
}
