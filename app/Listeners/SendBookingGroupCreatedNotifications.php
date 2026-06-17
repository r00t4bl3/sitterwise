<?php

namespace App\Listeners;

use App\Enums\BookingPaymentStatus;
use App\Events\BookingGroupCreated;
use App\Mail\AdminGroupBookingCreatedMail;
use App\Notifications\ClientGroupBookingCreatedNotification;
use App\Notifications\ClientPaymentRequiredNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendBookingGroupCreatedNotifications implements ShouldQueue
{
    public function handle(BookingGroupCreated $event): void
    {
        $group = $event->bookingGroup;

        // 1. Notify admin (always)
        Mail::to(config('mail.from.address'))->send(new AdminGroupBookingCreatedMail($group));

        // 2. Notify the Client (gated by payment method)
        $client = $group->client;
        $firstBooking = $group->bookings()->first();

        if ($client && $client->user) {
            $needsPaymentMethod = $group->requires_payment
                && $firstBooking
                && $firstBooking->payment_status === BookingPaymentStatus::Pending->value
                && ! $client->hasPaymentMethod();

            if ($needsPaymentMethod) {
                // Defer ClientGroupBookingCreatedNotification until payment method is added
                $client->user->notify(new ClientPaymentRequiredNotification($firstBooking));
            } else {
                $client->user->notify(new ClientGroupBookingCreatedNotification($group));
            }
        }
    }
}
