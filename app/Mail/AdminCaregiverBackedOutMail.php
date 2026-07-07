<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;

class AdminCaregiverBackedOutMail extends SendGridDynamicMail implements ShouldQueue
{
    public function __construct(
        public string $caregiverName,
        public int $caregiverId,
        public int $bookingId,
        public string $reason,
    ) {}

    protected function templateId(): string
    {
        return 'd-44ad02d6c50343709900263b8d1c3b28';
    }

    protected function templateData(): array
    {
        return [
            'booking_id' => $this->bookingId,
            'caregiver_name' => $this->caregiverName,
            'reason' => $this->reason,
            'jobs_url' => url('/caregivers/'.$this->caregiverId.'/jobs'),
        ];
    }

    protected function subjectLine(): string
    {
        return "Caregiver {$this->caregiverName} has backed out of job #{$this->bookingId}";
    }

    protected function bladeView(): ?string
    {
        return 'emails.admin-caregiver-backed-out';
    }

    protected function shouldBccTeam(): bool
    {
        return false;
    }
}
