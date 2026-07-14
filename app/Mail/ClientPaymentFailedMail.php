<?php

namespace App\Mail;

use App\Models\Booking;

class ClientPaymentFailedMail extends SendGridDynamicMail
{
    public function __construct(
        public Booking $booking,
        public string $errorMessage = '',
    ) {}

    protected function templateId(): string
    {
        return 'd-7b4a3691a5f44f3392415ed14143cdd5';
    }

    protected function templateData(): array
    {
        $group = $this->booking->bookingGroup;

        $data = [
            'client_first_name' => $this->booking->client?->first_name ?? $group?->client_first_name ?? 'there',
            'service_type' => $this->booking->service_type_label ?? 'Childcare',
            'service_date' => $this->serviceDate(),
            'total_amount' => number_format((float) $this->booking->total_amount, 2),
            'update_payment_url' => route('payments.index'),
            'booking_id' => (string) $this->booking->id,
        ];

        $declineReason = $this->declineReason();

        if ($declineReason !== null) {
            $data['decline_reason'] = $declineReason;
        }

        return $data;
    }

    protected function subjectLine(): string
    {
        return "Action needed: your Sitterwise payment didn't go through";
    }

    protected function bladeView(): ?string
    {
        return 'emails.client-payment-failed';
    }

    /**
     * Extra values the Blade preview needs that aren't public properties.
     *
     * @return array<string, mixed>
     */
    protected function bladeData(): array
    {
        return $this->templateData();
    }

    protected function shouldBccTeam(): bool
    {
        return false;
    }

    protected function serviceDate(): string
    {
        return $this->booking->start_datetime
            ? $this->booking->start_datetime->copy()->setTimezone('America/Los_Angeles')->format('l, F j, Y')
            : '—';
    }

    /**
     * A client-friendly decline reason. The Stripe error message is already
     * customer-facing (e.g. "Your card was declined."); return null when we have
     * nothing useful so the template's {{#if}} block simply omits the line.
     */
    protected function declineReason(): ?string
    {
        $message = trim($this->errorMessage);

        return $message !== '' ? $message : null;
    }
}
