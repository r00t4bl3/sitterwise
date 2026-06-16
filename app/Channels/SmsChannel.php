<?php

namespace App\Channels;

use App\Models\User;
use App\Services\TwilioService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\TwilioException;

class SmsChannel
{
    public function __construct(
        private TwilioService $twilio
    ) {}

    public function send(User $notifiable, Notification $notification): void
    {
        $message = $notification->toSms($notifiable);

        if (! $message) {
            return;
        }

        $to = $notifiable->routeNotificationForSms();

        if (! $to) {
            return;
        }

        try {
            $this->twilio->send($to, $message->message);
        } catch (TwilioException $e) {
            Log::warning('SMS failed for user {user}: {error}', [
                'user' => $notifiable->getKey(),
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
