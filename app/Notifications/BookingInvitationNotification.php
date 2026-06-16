<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Mail\CaregiverBookingInvitationMail;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class BookingInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', SmsChannel::class, WebPushChannel::class];
    }

    public function toMail(object $notifiable)
    {
        $address = $notifiable->routeNotificationFor('mail', $this);

        return (new CaregiverBookingInvitationMail($this->booking))->to($address);
    }

    public function toSms(object $notifiable): object
    {
        $clientName = ($this->booking->client?->first_name ?? $this->booking->client_first_name)
            .' '.($this->booking->client?->last_name ?? $this->booking->client_last_name);

        $city = $this->booking->bookingGroup?->address_city;

        $start = $this->booking->start_datetime->copy()->setTimezone('America/Los_Angeles');
        $end = $this->booking->end_datetime->copy()->setTimezone('America/Los_Angeles');

        $date = $start->format('n/j/y');
        $startTime = $start->format('g:ia');
        $endTime = $end->format('g:ia');

        $location = $city ? " ({$city})" : '';

        return (object) [
            'message' => "{$clientName}{$location}: {$date} {$startTime}-{$endTime}",
        ];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        $clientName = ($this->booking->client?->first_name ?? $this->booking->client_first_name)
            .' '.($this->booking->client?->last_name ?? $this->booking->client_last_name);

        $city = $this->booking->bookingGroup?->address_city;

        $start = $this->booking->start_datetime->copy()->setTimezone('America/Los_Angeles');
        $end = $this->booking->end_datetime->copy()->setTimezone('America/Los_Angeles');

        $startFormatted = $start->format('n/j/y g:ia');
        $endFormatted = $end->format('g:ia');

        $location = $city ? " in {$city}" : '';

        return (new WebPushMessage)
            ->title("New {$this->booking->service_type_label} job available")
            ->body("{$clientName}{$location}: {$startFormatted}-{$endFormatted}")
            ->icon('/icon-192.png')
            ->badge('/icon-72.png')
            ->data(['url' => '/caregiver/jobs'])
            ->options(['TTL' => 43200]);
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
