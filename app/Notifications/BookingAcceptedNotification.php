<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Mail\AdminBookingAcceptedMail;
use App\Mail\CaregiverBookingAcceptedMail;
use App\Mail\ClientBookingAcceptedMail;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BookingAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail'];

        if ($notifiable->isClient()) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable)
    {
        if ($notifiable->isAdmin()) {
            return new AdminBookingAcceptedMail($this->booking);
        }

        if ($notifiable->isCaregiver()) {
            return new CaregiverBookingAcceptedMail($this->booking);
        }

        return new ClientBookingAcceptedMail($this->booking);
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms(object $notifiable): object
    {
        $clientLastName = $this->booking->client?->last_name ?? $this->booking->client_last_name;
        $caregiverName = $this->booking->caregiver ? ($this->booking->caregiver->first_name.' '.$this->booking->caregiver->last_name) : 'A sitter';
        $date = $this->booking->start_datetime->format('n/j/y');
        $time = $this->booking->start_datetime->format('g:i A');
        $profileLink = $this->booking->caregiver ? config('app.url').'/caregivers/'.$this->booking->caregiver->id : 'our platform';

        return (object) [
            'message' => "Hello {$clientLastName} family! We wanted to let you know your caregiver on {$date} at {$time} will be {$caregiverName}. Here is her profile: {$profileLink}. We look forward to helping your family!",
        ];
    }

    public function toArray(object $notifiable): array
    {
        $caregiverName = $this->booking->caregiver ? ($this->booking->caregiver->first_name.' '.$this->booking->caregiver->last_name) : 'A sitter';
        $clientName = ($this->booking->client?->first_name ?? $this->booking->client_first_name).' '.($this->booking->client?->last_name ?? $this->booking->client_last_name);
        $date = $this->booking->start_datetime->format('M j, Y');

        if ($notifiable->isAdmin()) {
            return [
                'booking_id' => $this->booking->id,
                'title' => 'Booking Confirmed',
                'message' => "{$caregiverName} has been confirmed for {$clientName}'s booking.",
                'type' => 'booking_accepted',
            ];
        }

        if ($notifiable->isCaregiver()) {
            return [
                'booking_id' => $this->booking->id,
                'title' => 'Assignment Confirmed',
                'message' => "You are scheduled for {$clientName} on {$date}.",
                'type' => 'booking_accepted',
            ];
        }

        return [
            'booking_id' => $this->booking->id,
            'title' => 'Sitter Matched',
            'message' => "Great news! {$caregiverName} has been matched for your booking on {$date}.",
            'type' => 'booking_accepted',
        ];
    }
}
