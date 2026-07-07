<?php

namespace App\Console\Commands;

use App\Mail\SendGridTemplate;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class TestEmailCommand extends Command
{
    protected $signature = 'test:email
        {--to= : The recipient email address}
        {--mailer=smtp : Which mailer to send through (e.g. smtp, sendgrid, log)}
        {--booking= : Send the SendGridTemplate POC using this booking id (or "first") instead of the plain test email}';

    protected $description = 'Send a test email to verify email configuration';

    public function handle(): int
    {
        $to = $this->option('to') ?? $this->ask('Recipient email address?');

        $mailer = $this->option('mailer');
        $available = array_keys(config('mail.mailers', []));
        if (! in_array($mailer, $available, true)) {
            $this->error("Unknown mailer '{$mailer}'. Available: ".implode(', ', $available));

            return Command::FAILURE;
        }

        // Set the DEFAULT mailer (not just the per-send mailer): the SendGrid driver
        // only embeds the dynamic template when config('mail.default') === 'sendgrid',
        // so this single line governs both the trait gate and the transport.
        config(['mail.default' => $mailer]);

        $this->info("Sending test email to: {$to}");
        $this->info("Mailer: {$mailer}");

        $bookingOption = $this->option('booking');

        if ($bookingOption !== null) {
            return $this->sendSendGridTemplate($to, $bookingOption, $mailer);
        }

        Notification::route('mail', $to)->notify(new TestEmailNotification);

        $this->info('Test email sent successfully!');

        return Command::SUCCESS;
    }

    private function sendSendGridTemplate(string $to, string $bookingOption, string $mailer): int
    {
        $booking = is_numeric($bookingOption)
            ? Booking::find((int) $bookingOption)
            : Booking::whereNotNull('booking_group_id')->first();

        if (! $booking) {
            $this->error('No matching booking found (needs a booking with a booking group for toEmailData).');

            return Command::FAILURE;
        }

        $this->info("Payload: SendGridTemplate for booking #{$booking->id}");
        $this->line($mailer === 'sendgrid'
            ? '-> mail.default=sendgrid: the branded SendGrid dynamic template will render.'
            : "-> mail.default={$mailer}: sendgrid() is a no-op, so the Blade body renders (local/Mailpit).");

        Mail::to($to)->send(new SendGridTemplate($booking));

        $this->info('Test email sent successfully!');

        return Command::SUCCESS;
    }
}

class TestEmailNotification extends \Illuminate\Notifications\Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Test Email - '.config('app.name'))
            ->line('This is a test email from '.config('app.name'))
            ->line('If you received this, your email configuration is working correctly.')
            ->line('Mailer: '.config('mail.default'))
            ->line('Sent at: '.now()->toDateTimeString());
    }
}
