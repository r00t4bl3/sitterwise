<?php

namespace App\Channels;

use App\Models\User;
use App\Services\TwilioService;
use Illuminate\Notifications\Notification;

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

        $this->twilio->send($to, $message->message);
    }
}
