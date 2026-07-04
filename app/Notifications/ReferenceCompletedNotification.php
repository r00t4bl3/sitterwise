<?php

namespace App\Notifications;

use App\Mail\ReferenceCompletedMail;
use App\Models\ReferenceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReferenceCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ReferenceRequest $reference,
        public string $applicantName,
        public ?string $reviewUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable)
    {
        $address = $notifiable->routeNotificationFor('mail', $this);

        return (new ReferenceCompletedMail(
            $this->reference,
            $this->applicantName,
            $this->reviewUrl,
        ))->to($address);
    }
}
