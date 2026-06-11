<?php

namespace App\Notifications;

use App\Mail\AdminCaregiverArchivedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AdminCaregiverArchivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $caregiverName,
        public int $caregiverId,
        public int $daysOnHold,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable)
    {
        $address = $notifiable->routeNotificationFor('mail', $this);

        return (new AdminCaregiverArchivedMail(
            caregiverName: $this->caregiverName,
            caregiverId: $this->caregiverId,
            daysOnHold: $this->daysOnHold,
        ))->to($address);
    }
}
