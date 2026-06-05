<?php

namespace App\Notifications;

use App\Mail\AdminBookingCreatedMail;
use App\Mail\ClientBookingCreatedMail;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BookingCreatedNotification extends Notification implements ShouldQueue
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

        if ($notifiable->isAdmin()) {
            return (new AdminBookingCreatedMail($this->booking))->to($address);
        }

        return (new ClientBookingCreatedMail($this->booking))->to($address);
    }

    public function toArray(object $notifiable): array
    {
        $clientName = ($this->booking->client?->first_name ?? $this->booking->client_first_name).' '.($this->booking->client?->last_name ?? $this->booking->client_last_name);

        if ($notifiable->isAdmin()) {
            return [
                'booking_id' => $this->booking->id,
                'title' => 'New Booking Request',
                'message' => "New request received from {$clientName}.",
                'type' => 'booking_created',
            ];
        }

        return [
            'booking_id' => $this->booking->id,
            'title' => 'Booking Received',
            'message' => "We've received your request for {$this->booking->service_type_label}. We'll notify you once a sitter is matched.",
            'type' => 'booking_created',
        ];
    }
}
