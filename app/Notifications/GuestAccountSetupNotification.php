<?php

namespace App\Notifications;

use App\Mail\GuestAccountSetupMail;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class GuestAccountSetupNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public string $resetToken,
    ) {}

    protected function channels(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable)
    {
        return new GuestAccountSetupMail($this->booking, $this->resetToken);
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
