<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;

class CaregiverOnHoldCheckinMail extends SendGridDynamicMail implements ShouldQueue
{
    public function __construct(
        public string $caregiverName,
        public int $daysOnHold,
        public string $tier,
    ) {}

    protected function templateId(): string
    {
        return 'd-4de573218a71436d849f2c67a6d9e6e7';
    }

    protected function templateData(): array
    {
        return [
            'caregiver_name' => $this->caregiverName,
            'days_on_hold' => $this->daysOnHold,
            'is_final' => $this->tier === 'final',
            'is_reminder' => $this->tier === 'reminder',
            'pause_settings_url' => url('/settings/caregiver/pause'),
        ];
    }

    protected function subjectLine(): string
    {
        return match ($this->tier) {
            'final' => 'Action needed: Your Sitterwise account will be archived soon',
            'reminder' => "It's been {$this->daysOnHold} days — ready to come back?",
            default => 'Haven\'t seen you in a while — checking in!',
        };
    }

    protected function bladeView(): ?string
    {
        return 'emails.caregiver-on-hold-checkin';
    }
}
