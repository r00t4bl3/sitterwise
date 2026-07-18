<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\CalendarFeedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarSyncController extends Controller
{
    public function __construct(private CalendarFeedService $calendarFeed) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->isCaregiver()) {
            return redirect()->route('profile.edit');
        }

        $token = $this->calendarFeed->ensureToken($user->caregiver);

        return Inertia::render('settings/calendar-sync', [
            'feedUrl' => route('calendar.feed', ['token' => $token]),
        ]);
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isCaregiver()) {
            return redirect()->route('profile.edit');
        }

        $this->calendarFeed->regenerateToken($user->caregiver);

        return redirect()->route('settings.caregiver.calendar-sync')
            ->with('success', 'Your calendar link has been regenerated. Update it in your calendar app.');
    }
}
