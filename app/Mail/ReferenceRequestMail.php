<?php

namespace App\Mail;

class ReferenceRequestMail extends SendGridDynamicMail
{
    public function __construct(
        public string $referenceName,
        public string $applicantName,
        public string $token,
    ) {}

    protected function templateId(): string
    {
        return 'd-0533743f636141fe880c9bbe8097b084';
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
        return "Sitterwise Reference Request — {$this->applicantName}";
    }

    protected function bladeView(): ?string
    {
        return 'emails.reference-request';
    }
}
