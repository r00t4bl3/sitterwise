<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Sichikawa\LaravelSendgridDriver\SendGrid;

class ClientPaymentRequiredMail extends Mailable
{
    use Queueable, SendGrid, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        $data = $this->booking->toEmailData();
        $data['payment_link'] = route('bookings.show', $this->booking);

        $data['first_name'] = $data['client_first_name'] ?? '';
        $data['service_type'] = $data['service_requested'] ?? '';

        $bookingDate = $data['date'] ?? '';
        $group = $this->booking->bookingGroup;
        if ($group && ($group->bookings_count ?? $group->bookings()->count()) > 1) {
            $firstBooking = $group->bookings()->orderBy('start_datetime')->first();
            if ($firstBooking) {
                $firstDate = $firstBooking->start_datetime
                    ->setTimezone('America/Los_Angeles')
                    ->format('l, F j, Y');
                $extraCount = $group->bookings()->count() - 1;
                $bookingDate = $firstDate." (+{$extraCount} more)";
            }
        }
        $data['booking_date'] = $bookingDate;

        $this->sendgrid([
            'personalizations' => [
                [
                    'dynamic_template_data' => $data,
                ],
            ],
            'template_id' => 'd-9f4b24bb450140d9bd2c1628b705fbc1',
        ]);

        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: 'Payment Required',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: ' ',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
