<?php

namespace App\Listeners;

use App\Events\BookingGroupCreated;
use App\Mail\ClientGroupBookingCreatedMail;
use App\Models\User;
use App\Notifications\AdminGroupBookingCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class SendBookingGroupCreatedNotifications implements ShouldQueue
{
    public function handle(BookingGroupCreated $event): void
    {
        $group = $event->bookingGroup;

        // 1. Notify the Client
        $client = $group->client;
        if ($client && $client->user) {
            Mail::to($client->user->email)->send(new ClientGroupBookingCreatedMail($group));
        }

        // 2. Notify all Admins
        $admins = User::where('role', 'admin')->get();
        Notification::send($admins, new AdminGroupBookingCreatedNotification($group));
    }
}
