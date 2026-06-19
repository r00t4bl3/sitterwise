<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Mail\BookingReviewReminderMail;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class BookingReviewReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if ($this->booking->end_datetime->lt(now()->subHours(48))) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable)
    {
        $reviewUrl = URL::temporarySignedRoute('review.create', now()->addDays(14), [
            'booking' => $this->booking->ulid,
        ]);

        return (new BookingReviewReminderMail($this->booking, $reviewUrl))
            ->to($notifiable->routeNotificationFor('mail', $this));
    }

    public function toSms(object $notifiable): object
    {
        $caregiverName = $this->booking->caregiver
            ? $this->booking->caregiver->first_name.' '.$this->booking->caregiver->last_name
            : 'your sitter';

        $reviewUrl = URL::temporarySignedRoute('review.create', now()->addDays(14), [
            'booking' => $this->booking->ulid,
        ]);

        return (object) [
            'message' => "How was your experience with {$caregiverName}? We'd love your feedback! Leave a review here: {$reviewUrl}",
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_ulid' => $this->booking->ulid,
            'caregiver_name' => $this->booking->caregiver
                ? $this->booking->caregiver->first_name.' '.$this->booking->caregiver->last_name
                : 'Unknown',
            'message' => 'We\'d love your feedback! Leave a review for your recent booking.',
            'review_url' => URL::temporarySignedRoute('review.create', now()->addDays(14), [
                'booking' => $this->booking->ulid,
            ]),
        ];
    }
}
