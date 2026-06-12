<?php

namespace App\Notifications;

use App\Mail\ClientPaymentRequiredMail;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ClientPaymentRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): ClientPaymentRequiredMail
    {
        $mail = new ClientPaymentRequiredMail($this->booking);

        $mail->to($notifiable->routeNotificationForMail());

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'booking_ulid' => $this->booking->ulid,
            'type' => 'payment_required',
            'message' => 'Payment is required for your upcoming booking.',
        ];
    }
}
