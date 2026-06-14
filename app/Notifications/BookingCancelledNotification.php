<?php

namespace App\Notifications;

use App\Mail\AdminBookingCancelledMail;
use App\Mail\CaregiverBookingCancelledMail;
use App\Mail\ClientBookingCancelledMail;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BookingCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public string $reason,
        public User $cancelledBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable)
    {
        $address = $notifiable->routeNotificationFor('mail', $this);

        if ($notifiable->isAdmin()) {
            return (new AdminBookingCancelledMail(
                booking: $this->booking,
                reason: $this->reason,
                cancelledBy: $this->cancelledBy,
            ))->to($address);
        }

        if ($notifiable->id === $this->booking->caregiver?->user?->id) {
            return (new CaregiverBookingCancelledMail(
                booking: $this->booking,
                reason: $this->reason,
            ))->to($address);
        }

        return (new ClientBookingCancelledMail(
            booking: $this->booking,
            reason: $this->reason,
        ))->to($address);
    }
}
