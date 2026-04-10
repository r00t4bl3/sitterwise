<?php

namespace App\Console\Commands;

use App\Services\TwilioService;
use Illuminate\Console\Command;

class TestSmsCommand extends Command
{
    protected $signature = 'test:sms {--to= : The recipient phone number}';

    protected $description = 'Send a test SMS to verify Twilio configuration';

    public function handle(TwilioService $twilio): int
    {
        $to = $this->option('to') ?? $this->ask('Recipient phone number? (E.164 format, e.g. +1234567890)');

        $this->info("Sending test SMS to: {$to}");
        $this->info('Twilio Phone Number: '.config('services.twilio.phone_number'));

        $message = '['.config('app.name').'] Test SMS. If you received this, your Twilio configuration is working correctly. Sent at: '.now()->toDateTimeString();

        $result = $twilio->send($to, $message);

        if ($result['success']) {
            $this->info('SMS sent successfully!');
            $this->info('SID: '.$result['sid']);
            $this->info('Status: '.$result['status']);
        } else {
            $this->error('Failed to send SMS');
        }

        return Command::SUCCESS;
    }
}
