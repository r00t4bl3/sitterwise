<?php

namespace App\Mail;

use App\Models\Booking;

class BookingReviewReminderMail extends SendGridDynamicMail
{
    public function __construct(
        public Booking $booking,
        public string $reviewUrl,
    ) {}

    protected function shouldBccTeam(): bool
    {
        return false;
    }

    protected function templateId(): string
    {
        return 'd-ed4e08ffb28648f4aee1485389653810';
    }

    protected function templateData(): array
    {
        return [
            'caregiver_name' => $this->caregiverName(),
            'service_date' => $this->serviceDate(),
            'review_url' => $this->reviewUrl,
        ];
    }

    protected function subjectLine(): string
    {
        return 'How was your Sitterwise experience? Share your review!';
    }

    protected function bladeView(): ?string
    {
        return 'emails.review-reminder';
    }

    protected function bladeData(): array
    {
        return [
            'caregiverName' => $this->caregiverName(),
            'date' => $this->serviceDate(),
            'reviewUrl' => $this->reviewUrl,
        ];
    }

    private function caregiverName(): string
    {
        return $this->booking->caregiver
            ? $this->booking->caregiver->first_name.' '.$this->booking->caregiver->last_name
            : 'your sitter';
    }

    private function serviceDate(): string
    {
        return $this->booking->end_datetime->copy()->setTimezone('America/Los_Angeles')->format('l, F j, Y');
    }
}
