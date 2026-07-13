<?php

namespace App\Http\Middleware;

use App\Enums\CaregiverStatus;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'google_places_api_key' => config('services.google_places.api_key'),
            'auth' => [
                'user' => $request->user()?->only(['id', 'name', 'email', 'role', 'profile_photo_path', 'profile_photo_url']),
            ],
            'vapid_public_key' => config('webpush.vapid.public_key'),
            'supports_push' => config('app.env') === 'local' || $request->secure(),
            'push_subscribed' => $request->user()?->pushSubscriptions()->count() > 0,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'caregiverStatuses' => CaregiverStatus::toArray(),
            'booking_minimum_hours' => (int) Settings::get('bookings.minimum_hours', 4),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
        ];
    }
}
