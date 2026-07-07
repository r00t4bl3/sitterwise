<?php

namespace App\Mail;

use App\Models\Booking;

class AdminPaymentFailedMail extends SendGridDynamicMail
{
    public function __construct(
        public Booking $booking,
        public int $attemptCount,
        public string $errorMessage,
    ) {}

    protected function templateId(): string
    {
        return 'd-ffd19317faa641ac83e898f159ed7692';
    }

    protected function templateData(): array
    {
        $group = $this->booking->bookingGroup;

        $clientName = trim(
            ($this->booking->client?->first_name ?? $group?->client_first_name).' '.
            ($this->booking->client?->last_name ?? $group?->client_last_name)
        );

        $serviceDate = $this->booking->start_datetime
            ? $this->booking->start_datetime->copy()->setTimezone('America/Los_Angeles')->format('M j, Y')
            : '—';

        return [
            'error_message' => $this->errorMessage,
            'booking_id' => $this->booking->id,
            'client_name' => $clientName ?: 'N/A',
            'service_type' => $this->booking->service_type_label,
            'service_date' => $serviceDate,
            'attempt_count' => $this->attemptCount,
            'total_amount' => number_format((float) $this->booking->total_amount, 2),
        ];
    }

    protected function subjectLine(): string
    {
        return 'Payment Failed - Booking #'.$this->booking->id;
    }

    protected function bladeView(): ?string
    {
        return 'emails.admin-payment-failed';
    }

    protected function shouldBccTeam(): bool
    {
        return false;
    }
}
