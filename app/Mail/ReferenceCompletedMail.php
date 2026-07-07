<?php

namespace App\Mail;

use App\Models\ReferenceRequest;

class ReferenceCompletedMail extends SendGridDynamicMail
{
    public function __construct(
        public ReferenceRequest $reference,
        public string $applicantName,
        public ?string $reviewUrl = null,
    ) {}

    protected function templateId(): string
    {
        return 'd-622707caa2b54456a6921f032fb1af3e';
    }

    protected function templateData(): array
    {
        return [
            'applicant_name' => $this->applicantName,
            'reference_name' => $this->reference->reference_name,
        ];
    }

    protected function subjectLine(): string
    {
        return "Reference Completed — {$this->reference->reference_name} for {$this->applicantName}";
    }

    protected function bladeView(): ?string
    {
        return 'emails.reference-completed';
    }

    protected function shouldBccTeam(): bool
    {
        // Admin-facing: addressed to the team directly, so no team BCC.
        return false;
    }
}
