<?php

namespace App\Notifications;

use App\Mail\ReferenceCompletedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReferenceCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $referenceName,
        public string $applicantName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable)
    {
        $address = $notifiable->routeNotificationFor('mail', $this);

        return (new ReferenceCompletedMail(
            $this->referenceName,
            $this->applicantName,
        ))->to($address);
    }
}
