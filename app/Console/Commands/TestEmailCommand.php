<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;

class TestEmailCommand extends Command
{
    protected $signature = 'test:email {--to= : The recipient email address}';

    protected $description = 'Send a test email to verify email configuration';

    public function handle(): int
    {
        $to = $this->option('to') ?? $this->ask('Recipient email address?');

        $this->info("Sending test email to: {$to}");
        $this->info('Current mailer: '.config('mail.default'));

        Notification::route('mail', $to)->notify(new TestEmailNotification);

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
