<?php

namespace App\Mail;

class AdminNewApplicationMail extends SendGridDynamicMail
{
    public function __construct(
        public string $applicantName,
        public string $applicantEmail,
        public int $applicationId,
    ) {}

    protected function templateId(): string
    {
        return 'd-15f3364a4b4f493a9caa6e7031d96685';
    }

    protected function templateData(): array
    {
        return [
            'applicant_name' => $this->applicantName,
            'applicant_email' => $this->applicantEmail,
            'application_url' => route('applications.show', $this->applicationId),
        ];
    }

    protected function subjectLine(): string
    {
        return "New Caregiver Application — {$this->applicantName}";
    }

    protected function bladeView(): ?string
    {
        return 'emails.admin-new-application';
    }

    protected function shouldBccTeam(): bool
    {
        return false;
    }
}
