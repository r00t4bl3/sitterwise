<?php

namespace App\Notifications;

use App\Mail\AdminCaregiverBackedOutMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AdminCaregiverBackedOutNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $caregiverName,
        public int $caregiverId,
        public int $bookingId,
        public string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable)
    {
        $address = $notifiable->routeNotificationFor('mail', $this);

        return (new AdminCaregiverBackedOutMail(
            caregiverName: $this->caregiverName,
            caregiverId: $this->caregiverId,
            bookingId: $this->bookingId,
            reason: $this->reason,
        ))->to($address);
    }
}
