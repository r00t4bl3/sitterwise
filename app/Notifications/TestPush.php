<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class TestPush extends Notification
{
    use Queueable;

    public function __construct(
        public string $title = 'Test Notification',
        public string $body = 'This is a test push from Sitterwise!',
        public string $url = '/'
    ) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon('/icon-192.png')
            ->badge('/icon-72.png')
            ->data(['url' => $this->url])
            ->options(['TTL' => 300, 'urgency' => 'normal']);
    }
}
