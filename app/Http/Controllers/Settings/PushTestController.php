<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Notifications\TestPush;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PushTestController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->pushSubscriptions()->count() === 0) {
            return back()->with('error', 'No push subscription found. Subscribe to push notifications first.');
        }

        $user->notify(new TestPush(
            title: 'Test Notification',
            body: 'Push notifications are working!',
            url: '/settings/profile',
        ));

        return back()->with('success', 'Test push notification sent!');
    }
}
