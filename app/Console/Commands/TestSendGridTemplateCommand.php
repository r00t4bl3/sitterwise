<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Console\Command;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Sichikawa\LaravelSendgridDriver\SendGrid;

class TestSendGridTemplateCommand extends Command
{
    protected $signature = 'test:sendgrid-template
        {--to= : Recipient email address}
        {--booking= : Booking ID}
        {--template= : SendGrid template ID (numeric part only, e.g. 345)}';

    protected $description = 'Send a test email using a SendGrid dynamic template';

    public function handle(): int
    {
        $to = $this->option('to');
        $bookingId = $this->option('booking');
        $templateId = $this->option('template');

        if (! $to || ! $bookingId || ! $templateId) {
            $this->error('--to, --booking, and --template are all required.');

            return Command::FAILURE;
        }

        $booking = Booking::find((int) $bookingId);

        if (! $booking) {
            $this->error("Booking #{$bookingId} not found.");

            return Command::FAILURE;
        }

        $fullTemplateId = 'd-'.$templateId;

        $this->info("Sending to: {$to}");
        $this->info("Booking: #{$booking->id}");
        $this->info("Template: {$fullTemplateId}");

        config(['mail.default' => 'sendgrid']);

        Mail::to($to)->send(new class($booking, $fullTemplateId) extends Mailable
        {
            use Queueable, SendGrid, SerializesModels;

            public function __construct(
                public Booking $booking,
                public string $templateId,
            ) {}

            public function envelope(): Envelope
            {
                $this->sendgrid([
                    'personalizations' => [
                        ['dynamic_template_data' => $this->booking->toEmailData()],
                    ],
                    'template_id' => $this->templateId,
                ]);

                return new Envelope(
                    from: config('mail.from.address', 'admin@sitterwise.io'),
                    subject: 'Test SendGrid Template',
                );
            }

            public function content(): Content
            {
                return new Content(
                    view: 'emails.sendgrid-poc',
                    with: [
                        'marker' => 'BLADE_BODY_RENDERED',
                        'bookingId' => $this->booking->id,
                    ],
                );
            }
        });

        $this->info('Test SendGrid template email sent successfully!');

        return Command::SUCCESS;
    }
}
