<?php

namespace App\Notifications;

use App\Mail\AdminPaymentFailedMail;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public int $attemptCount,
        public string $errorMessage,
        public string $recipientType
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): AdminPaymentFailedMail
    {
        $address = $notifiable->routeNotificationFor('mail', $this);

        return (new AdminPaymentFailedMail(
            $this->booking,
            $this->attemptCount,
            $this->errorMessage,
        ))->to($address);
    }

    public function toArray(object $notifiable): array
    {
        $client = $this->booking->client;

        if ($this->recipientType === 'client') {
            return [
                'title' => 'Payment Failed',
                'message' => "Payment for booking #{$this->booking->id} has failed. Please update your payment method to complete the payment.",
                'booking_id' => $this->booking->id,
                'attempt' => $this->attemptCount,
                'error' => $this->errorMessage,
            ];
        }

        return [
            'title' => 'Payment Failed - Booking #'.$this->booking->id,
            'message' => "Payment failed for booking #{$this->booking->id}. Client: {$client->full_name}. Attempt {$this->attemptCount}/4. Error: {$this->errorMessage}",
            'booking_id' => $this->booking->id,
            'client_id' => $client->id,
            'client_name' => $client->full_name,
            'attempt' => $this->attemptCount,
            'error' => $this->errorMessage,
        ];
    }
}
