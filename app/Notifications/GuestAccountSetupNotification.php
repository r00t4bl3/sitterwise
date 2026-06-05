<?php

namespace App\Notifications;

use App\Mail\GuestAccountSetupMail;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class GuestAccountSetupNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public string $resetToken,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable)
    {
        $address = $notifiable->routeNotificationFor('mail', $this);

        return (new GuestAccountSetupMail($this->booking, $this->resetToken))->to($address);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'title' => 'Account Created',
            'message' => 'Your Sitterwise account has been created. Set your password to log in and manage your bookings.',
            'type' => 'account_setup',
        ];
    }
}
