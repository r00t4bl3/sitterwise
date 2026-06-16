<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Sichikawa\LaravelSendgridDriver\SendGrid;

class CaregiverBookingCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SendGrid, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $reason,
    ) {}

    public function envelope(): Envelope
    {
        $data = $this->booking->toEmailData();

        $start = $this->booking->start_datetime->copy()->setTimezone('America/Los_Angeles');

        $data['service_time_pretty'] = $start->format('g:i A');
        $data['caregiver_first_name'] = $this->booking->caregiver?->first_name ?? 'Sitter';
        $data['cancellation_reason'] = $this->reason;

        $this->sendgrid([
            'personalizations' => [
                [
                    'dynamic_template_data' => $data,
                ],
            ],
            'template_id' => 'd-2a539fde38bb46788fc96baf7fb6366b',
        ]);

        return new Envelope(
            from: config('mail.from.address', 'admin@sitterwise.io'),
            subject: 'Job #'.$this->booking->id.' Has Been Cancelled',
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
