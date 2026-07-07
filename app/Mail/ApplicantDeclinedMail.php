<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;

class ApplicantDeclinedMail extends SendGridDynamicMail implements ShouldQueue
{
    public function __construct(
        public string $applicantName,
        public ?string $reason = null,
    ) {}

    protected function templateId(): string
    {
        return 'd-fbfcb36f2d69474eb764f82ad1dac84b';
    }

    protected function templateData(): array
    {
        $data = ['applicant_name' => $this->applicantName];

        // Optional field: omit the key entirely so the template's {{#if}} hides it.
        if ($this->reason !== null && $this->reason !== '') {
            $data['reason'] = $this->reason;
        }

        return $data;
    }

    protected function subjectLine(): string
    {
        return 'Update on your Sitterwise application';
    }

    protected function bladeView(): ?string
    {
        return 'emails.application-declined';
    }
}
