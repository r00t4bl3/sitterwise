<?php

namespace App\Mail;

class ApplicantConfirmationMail extends SendGridDynamicMail
{
    public function __construct(
        public string $applicantName,
        public string $statusToken,
    ) {}

    protected function templateId(): string
    {
        return 'd-46445a000ef24dc690dc7eda3f438f1e';
    }

    protected function templateData(): array
    {
        return [
            'applicant_name' => $this->applicantName,
            'status_url' => url('/caregiver/apply/status/'.$this->statusToken),
        ];
    }

    protected function subjectLine(): string
    {
        return 'Your Sitterwise Application Has Been Received';
    }

    protected function bladeView(): ?string
    {
        return 'emails.applicant-confirmation';
    }

    protected function bladeData(): array
    {
        return [
            'statusUrl' => url('/caregiver/apply/status/'.$this->statusToken),
        ];
    }
}
