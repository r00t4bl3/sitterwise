<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;

class CaregiverArchiveWarningMail extends SendGridDynamicMail implements ShouldQueue
{
    public function __construct(
        public string $caregiverName,
        public int $daysOnHold,
    ) {}

    protected function templateId(): string
    {
        return 'd-6a7ef80cc2b74e978c38d6c1ea897846';
    }

    protected function templateData(): array
    {
        return [
            'caregiver_name' => $this->caregiverName,
            'days_on_hold' => $this->daysOnHold,
            'pause_settings_url' => url('/settings/caregiver/pause'),
        ];
    }

    protected function subjectLine(): string
    {
        return 'Your Sitterwise account will be archived in 14 days';
    }

    protected function bladeView(): ?string
    {
        return 'emails.caregiver-archive-warning';
    }
}
