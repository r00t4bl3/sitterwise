<?php

namespace App\Notifications;

use App\Mail\CaregiverBookingInvitationMail;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BookingInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable)
    {
        $address = $notifiable->routeNotificationFor('mail', $this);

        return (new CaregiverBookingInvitationMail($this->booking))->to($address);
    }

    public function toArray(object $notifiable): array
    {
        $clientName = ($this->booking->client?->first_name ?? $this->booking->client_first_name);

        return [
            'booking_id' => $this->booking->id,
            'title' => 'New Job Invitation',
            'message' => "You have a new job invitation for {$clientName}. Click to view and claim.",
            'type' => 'booking_invitation',
        ];
    }
}
