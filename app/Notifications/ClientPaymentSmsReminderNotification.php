<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ClientPaymentSmsReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        return [SmsChannel::class];
    }

    public function toSms(object $notifiable): object
    {
        $data = $this->booking->toEmailData();
        $paymentLink = route('bookings.show', $this->booking);

        return (object) [
            'message' => "Hi {$data['client_first_name']}, this is Sitterwise following up on your {$data['date']} reservation. We're ready to match you with a caregiver as soon as your payment info is on file! Your card won't be charged until after care is complete. Add it here: {$paymentLink} Questions? Just reply or call 619-663-4379.",
        ];
    }
}
