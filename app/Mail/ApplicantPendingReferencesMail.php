<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;

class ApplicantPendingReferencesMail extends SendGridDynamicMail implements ShouldQueue
{
    public function __construct(
        public string $applicantName,
        public int $daysSinceSubmission,
    ) {}

    protected function templateId(): string
    {
        return 'd-eaa36d01d9e948849e15e2afadb8b71d';
    }

    protected function templateData(): array
    {
        return [
            'applicant_name' => $this->applicantName,
            'over_one_week' => $this->daysSinceSubmission >= 7,
        ];
    }

    protected function subjectLine(): string
    {
        return $this->daysSinceSubmission >= 7
            ? 'Still waiting on your references — Sitterwise'
            : 'Your Sitterwise references are still pending';
    }

    protected function bladeView(): ?string
    {
        return 'emails.applicant-pending-references';
    }
}
