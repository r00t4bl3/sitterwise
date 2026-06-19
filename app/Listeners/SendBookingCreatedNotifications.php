<?php

namespace App\Listeners;

use App\Enums\BookingPaymentStatus;
use App\Events\BookingCreated;
use App\Mail\AdminBookingCreatedMail;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\ClientPaymentRequiredNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendBookingCreatedNotifications implements ShouldQueue
{
    public static int $callCount = 0;

    public function handle(BookingCreated $event): void
    {
        // 1. Notify admin (always)
        Mail::to(config('mail.from.address'))->send(new AdminBookingCreatedMail($event->booking));

        // 2. Notify the Client (gated by payment method)
        if ($event->booking->client && $event->booking->client->user) {
            $needsPaymentMethod = $event->booking->requires_payment
                && $event->booking->payment_status === BookingPaymentStatus::Pending->value
                && ! $event->booking->client->hasPaymentMethod();

            if ($needsPaymentMethod) {
                // Defer BookingCreatedNotification until payment method is added
                $event->booking->client->user->notify(new ClientPaymentRequiredNotification($event->booking));
            } else {
                $event->booking->client->user->notify(new BookingCreatedNotification($event->booking));
            }
        }
    }
}
