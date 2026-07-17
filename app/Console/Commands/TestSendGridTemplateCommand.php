<?php

namespace App\Console\Commands;

use App\Mail\AdminBookingCancelledMail;
use App\Mail\AdminGroupBookingCreatedMail;
use App\Mail\AdminPaymentFailedMail;
use App\Mail\BookingReviewReminderMail;
use App\Mail\CaregiverBookingCancelledMail;
use App\Mail\CaregiverBookingInvitationMail;
use App\Mail\ClientBookingCancelledMail;
use App\Mail\ClientGroupBookingCreatedMail;
use App\Mail\ClientPaymentFailedMail;
use App\Mail\SendGridDynamicMail;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Console\Command;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Sichikawa\LaravelSendgridDriver\SendGrid;

class TestSendGridTemplateCommand extends Command
{
    protected $signature = 'test:sendgrid-template
        {--to= : Recipient email address}
        {--booking= : Booking ID}
        {--template= : SendGrid template ID (numeric part only, e.g. 345)}
        {--mailable= : Mailable class (short or full name, e.g. ClientGroupBookingCreatedMail)}';

    protected $description = 'Send a test email using a SendGrid dynamic template';

    public function handle(): int
    {
        $to = $this->option('to');
        $bookingId = $this->option('booking');
        $templateOption = $this->option('template');
        $mailableOption = $this->option('mailable');

        if (! $to || ! $bookingId) {
            $this->error('--to and --booking are required.');

            return Command::FAILURE;
        }

        $booking = Booking::find((int) $bookingId);

        if (! $booking) {
            $this->error("Booking #{$bookingId} not found.");

            return Command::FAILURE;
        }

        if ($mailableOption) {
            $class = $this->resolveClassName($mailableOption);

            if (! class_exists($class)) {
                $this->error("Mailable class not found: {$class}");

                return Command::FAILURE;
            }

            if (! is_subclass_of($class, SendGridDynamicMail::class)) {
                $this->error('Mailable must extend '.SendGridDynamicMail::class);

                return Command::FAILURE;
            }

            $mailable = $this->resolveMailable($class, $booking);
            $templateId = $templateOption ? 'd-'.$templateOption : (fn () => $this->templateId())->call($mailable);
            $templateData = (fn () => $this->templateData())->call($mailable);
            $mailableName = class_basename($class);
        } elseif ($templateOption) {
            $templateId = 'd-'.$templateOption;
            $templateData = $booking->toEmailData();
            $mailableName = null;
        } else {
            $this->error('--template is required when --mailable is not provided.');

            return Command::FAILURE;
        }

        $this->line('');
        $this->info('Summary:');
        $this->line("  Recipient:  {$to}");
        $this->line('  Mailable:   '.($mailableName ?? '–'));
        $this->line("  Booking:    #{$booking->id}");
        $this->line("  Template:   {$templateId}");
        $this->line('');

        if (! $this->confirm('Send this test email?', false)) {
            $this->info('Cancelled.');

            return Command::SUCCESS;
        }

        config(['mail.default' => 'sendgrid']);

        Mail::to($to)->send(new class($templateId, $templateData, $booking->id) extends Mailable
        {
            use Queueable, SendGrid, SerializesModels;

            public function __construct(
                public string $templateId,
                public array $templateData,
                public int $bookingId,
            ) {}

            public function envelope(): Envelope
            {
                $this->sendgrid([
                    'personalizations' => [
                        ['dynamic_template_data' => $this->templateData],
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
                        'bookingId' => $this->bookingId,
                    ],
                );
            }
        });

        $this->info('Test SendGrid template email sent successfully!');

        return Command::SUCCESS;
    }

    private function resolveClassName(string $name): string
    {
        return str_contains($name, '\\') ? $name : 'App\\Mail\\'.$name;
    }

    private function resolveMailable(string $class, Booking $booking): SendGridDynamicMail
    {
        return match ($class) {
            CaregiverBookingInvitationMail::class => new $class($booking),
            BookingReviewReminderMail::class => new $class($booking, route('home')),
            AdminBookingCancelledMail::class => new $class($booking, '[TEST] Reason', User::first() ?? throw new InvalidArgumentException('No user found for cancelled_by.')),
            CaregiverBookingCancelledMail::class => new $class($booking, '[TEST] Reason'),
            ClientBookingCancelledMail::class => new $class($booking, '[TEST] Reason'),
            ClientPaymentFailedMail::class => new $class($booking),
            AdminPaymentFailedMail::class => new $class($booking, 1, '[TEST] Error'),
            ClientGroupBookingCreatedMail::class => new $class($booking->bookingGroup ?? throw new InvalidArgumentException('Booking #'.$booking->id.' has no booking group.')),
            AdminGroupBookingCreatedMail::class => new $class($booking->bookingGroup ?? throw new InvalidArgumentException('Booking #'.$booking->id.' has no booking group.')),
            default => throw new InvalidArgumentException("Unsupported mailable: {$class}"),
        };
    }
}
