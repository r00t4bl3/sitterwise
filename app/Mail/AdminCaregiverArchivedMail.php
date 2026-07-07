<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;

class AdminCaregiverArchivedMail extends SendGridDynamicMail implements ShouldQueue
{
    public function __construct(
        public string $caregiverName,
        public int $caregiverId,
        public int $daysOnHold,
    ) {}

    protected function templateId(): string
    {
        return 'd-6c385f3b5a5f4e5180ccee4fedc09106';
    }

    protected function templateData(): array
    {
        return [
            'caregiver_name' => $this->caregiverName,
            'days_on_hold' => $this->daysOnHold,
            'caregiver_url' => url('/caregivers/'.$this->caregiverId),
        ];
    }

    protected function subjectLine(): string
    {
        return "Caregiver {$this->caregiverName} has been archived after {$this->daysOnHold} days on hold";
    }

    protected function bladeView(): ?string
    {
        return 'emails.admin-caregiver-archived';
    }

    protected function shouldBccTeam(): bool
    {
        return false;
    }
}
