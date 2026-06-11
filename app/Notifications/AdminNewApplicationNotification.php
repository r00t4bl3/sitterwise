<?php

namespace App\Notifications;

use App\Mail\AdminNewApplicationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AdminNewApplicationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $applicantName,
        public string $applicantEmail,
        public int $applicationId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable)
    {
        $address = $notifiable->routeNotificationFor('mail', $this);

        return (new AdminNewApplicationMail(
            $this->applicantName,
            $this->applicantEmail,
            $this->applicationId,
        ))->to($address);
    }
}
