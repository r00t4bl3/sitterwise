<?php

namespace App\Listeners;

use App\Events\BookingGroupCreated;
use App\Mail\AdminGroupBookingCreatedMail;
use App\Mail\ClientGroupBookingCreatedMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

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
        foreach ($admins as $admin) {
            Mail::to($admin->email)->send(new AdminGroupBookingCreatedMail($group));
        }
    }
}
