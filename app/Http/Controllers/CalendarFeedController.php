<?php

namespace App\Http\Controllers;

use App\Models\Caregiver;
use App\Services\CalendarFeedService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CalendarFeedController extends Controller
{
    public function __construct(private CalendarFeedService $calendarFeed) {}

    public function __invoke(Request $request, string $token): Response
    {
        $caregiver = Caregiver::where('calendar_feed_token', $token)->first();

        if (! $caregiver) {
            Log::warning('Calendar feed: invalid token', [
                'ip' => $request->ip(),
                'token' => $token,
            ]);

            abort(404);
        }

        return response($this->calendarFeed->buildCalendar($caregiver), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="sitterwise.ics"',
            'Cache-Control' => 'public, max-age=900',
        ]);
    }
}
