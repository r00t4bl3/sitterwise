<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;

class ApplicantHiredMail extends SendGridDynamicMail implements ShouldQueue
{
    public function __construct(
        public string $applicantName,
        public string $statusUrl,
    ) {}

    protected function templateId(): string
    {
        return 'd-4ff3875d2aab4fd293662eabb8aa6e77';
    }

    protected function templateData(): array
    {
        return [
            'applicant_name' => $this->applicantName,
            'status_url' => $this->statusUrl,
        ];
    }

    protected function subjectLine(): string
    {
        return "You're hired! Complete your onboarding at Sitterwise";
    }

    protected function bladeView(): ?string
    {
        return 'emails.application-hired';
    }
}
