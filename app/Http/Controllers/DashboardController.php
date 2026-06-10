<?php

namespace App\Http\Controllers;

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\CaregiverStatus;
use App\Enums\ClientType;
use App\Enums\DiscoverySource;
use App\Enums\LocationType;
use App\Enums\PetType;
use App\Enums\ServiceType;
use App\Enums\SitterPreference;
use App\Enums\TimeSlot;
use App\Models\AttributeDefinition;
use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Models\BookingRating;
use App\Models\Caregiver;
use App\Models\CaregiverApplication;
use App\Models\Client;
use App\Models\Hotel;
use App\Models\QuickLink;
use App\Models\ReferenceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $stats = [];
        $adminData = null;
        $caregiverData = null;
        $clientData = null;

        $bookingStatuses = array_map(
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'colors' => $case->colors(),
            ],
            BookingStatus::cases()
        );

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            $now = now();
            $monthStart = $now->copy()->startOfMonth();
            $monthEnd = $now->copy()->endOfMonth();
            $ytdStart = $now->copy()->startOfYear();

            $completedStatuses = [BookingStatus::Completed->value, BookingStatus::Paid->value];
            $activeStatuses = [BookingStatus::Received->value, BookingStatus::Pending->value, BookingStatus::Confirmed->value];

            $thisMonthCompleted = Booking::whereBetween('start_datetime', [$monthStart, $monthEnd])
                ->whereIn('status', $completedStatuses)
                ->count();

            $thisMonthUpcoming = Booking::whereBetween('start_datetime', [$monthStart, $monthEnd])
                ->whereIn('status', $activeStatuses)
                ->where('start_datetime', '>', $now)
                ->count();

            $ytdCompleted = Booking::whereBetween('start_datetime', [$ytdStart, $now])
                ->whereIn('status', $completedStatuses)
                ->count();

            $ytdUpcoming = Booking::whereBetween('start_datetime', [$ytdStart, $now])
                ->whereIn('status', $activeStatuses)
                ->where('start_datetime', '>', $now)
                ->count();

            $ytdTotal = $ytdCompleted + $ytdUpcoming;

            $lastYearDate = $now->copy()->subYear();
            $lastYearStart = $lastYearDate->copy()->startOfYear();
            $lytdTotal = Booking::whereBetween('start_datetime', [$lastYearStart, $lastYearDate])
                ->whereIn('status', [...$completedStatuses, ...$activeStatuses])
                ->count();

            $ytdPercentChange = $lytdTotal > 0
                ? round(($ytdTotal - $lytdTotal) / $lytdTotal * 100)
                : ($ytdTotal > 0 ? 100 : null);

            $unassigned = Booking::whereNull('caregiver_id')
                ->whereIn('status', [BookingStatus::Received->value, BookingStatus::Pending->value])
                ->inFuture()
                ->count();

            $missingPayment = Booking::whereHas('bookingGroup', fn ($q) => $q->where('requires_payment', true))
                ->whereIn('payment_status', [BookingPaymentStatus::Pending->value, BookingPaymentStatus::Failed->value])
                ->where('status', '!=', BookingStatus::Cancelled->value)
                ->count();

            $awaitingCheckout = Booking::whereNotNull('checkout_at')
                ->where('checkout_at', '<=', $now)
                ->whereNotIn('status', [...$completedStatuses, BookingStatus::Cancelled->value])
                ->count();

            $stats = [
                'totalCaregivers' => Caregiver::count(),
                'activeCaregivers' => Caregiver::where('status', CaregiverStatus::Active)->count(),
                'totalClients' => User::where('role', 'client')->count(),
                'totalBookings' => Booking::count(),
                'thisMonthCompleted' => $thisMonthCompleted,
                'thisMonthUpcoming' => $thisMonthUpcoming,
                'ytdCompleted' => $ytdCompleted,
                'ytdUpcoming' => $ytdUpcoming,
                'ytdPercentChange' => $ytdPercentChange,
                'ytdLastYearLabel' => (string) $lastYearDate->year,
                'troubledUnassigned' => $unassigned,
                'troubledMissingPayment' => $missingPayment,
                'troubledAwaitingCheckout' => $awaitingCheckout,
            ];

            $serviceTypes = array_map(
                fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->label(),
                ],
                ServiceType::cases()
            );

            $locationTypes = array_map(
                fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->label(),
                ],
                LocationType::cases()
            );

            $paymentStatuses = array_map(
                fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->label(),
                ],
                BookingPaymentStatus::cases()
            );

            $sitterPreferences = array_map(
                fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->label(),
                ],
                SitterPreference::cases()
            );

            $bookingAttributes = AttributeDefinition::where('type', 'booking')
                ->get()
                ->map(fn ($attr) => [
                    'id' => $attr->id,
                    'name' => $attr->name,
                    'slug' => $attr->slug,
                    'type' => $attr->type,
                    'options' => $attr->options ?? [],
                ])
                ->toArray();

            $adminData = [
                'bookingsNeedingAttention' => Booking::with(['client.user'])
                    ->whereNull('caregiver_id')
                    ->whereIn('status', [BookingStatus::Received->value, BookingStatus::Pending->value])
                    ->inFuture()
                    ->orderBy('start_datetime', 'asc')
                    ->limit(5)
                    ->get(),
                'todaysBookings' => Booking::with(['client.user', 'caregiver.user'])
                    ->inToday()
                    ->orderBy('start_datetime', 'asc')
                    ->get(),
                'recentBookings' => Booking::with(['client.user'])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(),
                'recentCaregivers' => Caregiver::with('user')
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(),
                'bookingStatuses' => $bookingStatuses,
                // Data for BookingSheet
                'clients' => Client::with('user')
                    ->get()
                    ->map(fn ($client) => [
                        'id' => $client->id,
                        'name' => $client->user->name,
                    ])
                    ->toArray(),
                'clientTypes' => array_map(
                    fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                    ClientType::cases()
                ),
                'discoverySources' => array_map(
                    fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                    DiscoverySource::cases()
                ),
                'hotels' => Hotel::all()
                    ->map(fn ($hotel) => [
                        'id' => $hotel->id,
                        'name' => $hotel->name,
                        'line1' => $hotel->line1,
                        'line2' => $hotel->line2,
                        'city' => $hotel->city,
                        'state' => $hotel->state,
                        'zip' => $hotel->zip,
                    ])
                    ->toArray(),
                'caregivers' => Caregiver::with('user')
                    ->get()
                    ->map(fn ($caregiver) => [
                        'id' => $caregiver->id,
                        'name' => $caregiver->user->name,
                    ])
                    ->toArray(),
                'serviceTypes' => $serviceTypes,
                'locationTypes' => $locationTypes,
                'paymentStatuses' => $paymentStatuses,
                'petTypes' => array_map(
                    fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                    PetType::cases()
                ),
                'bookingAttributes' => $bookingAttributes,
                'sitterPreferences' => $sitterPreferences,
                'quickLinks' => QuickLink::where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(),
                'pendingApplicationsCount' => CaregiverApplication::whereHas('caregiver.referenceRequests', function ($q) {
                    $q->pending();
                })->count(),
                'stuckReferencesCount' => ReferenceRequest::pending()
                    ->where('created_at', '<', now()->subDays(7))
                    ->count(),
                'reviewAnalytics' => [
                    'avgRatingAll' => round(BookingRating::where('ratable_type', Caregiver::class)->avg('rating') ?? 0, 2),
                    'avgRating30d' => round(BookingRating::where('ratable_type', Caregiver::class)
                        ->where('created_at', '>=', now()->subDays(30))
                        ->avg('rating') ?? 0, 2),
                    'avgRating90d' => round(BookingRating::where('ratable_type', Caregiver::class)
                        ->where('created_at', '>=', now()->subDays(90))
                        ->avg('rating') ?? 0, 2),
                    'totalReviews' => BookingRating::where('ratable_type', Caregiver::class)->count(),
                    'pendingReviewsCount' => Booking::whereIn('status', $completedStatuses)
                        ->whereDoesntHave('ratings', fn ($q) => $q->where('ratable_type', Caregiver::class))
                        ->count(),
                    'ratingDistribution' => [
                        BookingRating::where('ratable_type', Caregiver::class)->where('rating', 1)->count(),
                        BookingRating::where('ratable_type', Caregiver::class)->where('rating', 2)->count(),
                        BookingRating::where('ratable_type', Caregiver::class)->where('rating', 3)->count(),
                        BookingRating::where('ratable_type', Caregiver::class)->where('rating', 4)->count(),
                        BookingRating::where('ratable_type', Caregiver::class)->where('rating', 5)->count(),
                    ],
                ],
                'needsAttention' => [
                    // [
                    //     'key' => 'no_shows',
                    //     'label' => 'No-shows logged today',
                    //     'count' => 0,
                    //     'href' => '/bookings?filter=no_show',
                    //     'variant' => 'urgent',
                    // ],
                    [
                        'key' => 'applications_ready',
                        'label' => 'Applications ready to review',
                        'count' => Caregiver::where('status', CaregiverStatus::Applicant)
                            ->whereHas('application', fn ($q) => $q->whereNotNull('submitted_at'))
                            ->where(function ($q) {
                                $q->whereHas('referenceRequests', fn ($q) => $q->completed(), '>=', 2)
                                    ->orWhereHas('application', fn ($q) => $q->where('submitted_at', '<', now()->subDays(14)));
                            })
                            ->count(),
                        'href' => '/applications',
                        'variant' => 'default',
                    ],
                    [
                        'key' => 'onboarding_stalled',
                        'label' => 'Onboarding stalled > 7 days',
                        'count' => Caregiver::where('status', CaregiverStatus::HiredOnboarding)
                            ->where('created_at', '<', now()->subDays(7))
                            ->count(),
                        'href' => '/caregivers?filter=onboarding_stalled',
                        'variant' => 'warning',
                    ],
                    [
                        'key' => 'trustline_suspended',
                        'label' => 'Suspended for missed Trustline submission',
                        'count' => 0,
                        'href' => '/caregivers?filter=trustline_suspended',
                        'variant' => 'warning',
                    ],
                    [
                        'key' => 'compliance_expired',
                        'label' => 'Compliance currently expired (blocked)',
                        'count' => Caregiver::whereHas('certifications', function ($q) {
                            $q->where('certification_types.expires_required', true)
                                ->where('caregiver_certifications.expiration_date', '<', now());
                        })->count(),
                        'href' => '/caregivers?filter=compliance_expired',
                        'variant' => 'default',
                    ],
                    [
                        'key' => 'compliance_expiring',
                        'label' => 'Compliance expiring this month',
                        'count' => Caregiver::whereHas('certifications', function ($q) {
                            $q->where('certification_types.expires_required', true)
                                ->where('caregiver_certifications.expiration_date', '>=', now()->startOfMonth())
                                ->where('caregiver_certifications.expiration_date', '<=', now()->endOfMonth());
                        })->count(),
                        'href' => '/caregivers?filter=compliance_expiring',
                        'variant' => 'warning',
                    ],
                    [
                        'key' => 'inactive_45',
                        'label' => 'Caregivers inactive 45+ days',
                        'count' => Caregiver::where('status', CaregiverStatus::Inactive)
                            ->where('updated_at', '<', now()->subDays(45))
                            ->count(),
                        'href' => '/caregivers?filter=inactive_45',
                        'variant' => 'default',
                    ],
                    [
                        'key' => 'stuck_references',
                        'label' => 'References stuck (Day 7+ no response)',
                        'count' => ReferenceRequest::pending()
                            ->where('created_at', '<', now()->subDays(7))
                            ->count(),
                        'href' => '/applications?filter=stuck_references',
                        'variant' => 'default',
                    ],
                ],
            ];
        }

        if ($user->isCaregiver()) {
            $caregiver = $user->caregiver;
            $user->load(['caregiver']);

            if ($caregiver) {
                $availabilities = $caregiver->availabilities()
                    ->with('usedSlots')
                    ->inTheFuture()
                    ->orderBy('date')
                    ->limit(32)
                    ->get()
                    ->map(function ($availability) {
                        return [
                            'id' => $availability->id,
                            'date' => $availability->date->format('Y-m-d'),
                            'time_slots' => $availability->time_slots,
                            'specific_time' => $availability->specific_time,
                            'booked_slots' => $availability->usedSlots->pluck('time_slot')->toArray(),
                        ];
                    });

                $stats = [
                    'total_earned' => $caregiver->bookings()
                        ->whereIn('status', [BookingStatus::Completed->value, BookingStatus::Paid->value])
                        ->sum('paid_to_caregiver_total'),
                    'completed_jobs' => $caregiver->bookings()
                        ->whereIn('status', [BookingStatus::Completed->value, BookingStatus::Paid->value])
                        ->count(),
                    'rating' => $caregiver->rating,
                ];

                $allUpcoming = $caregiver->bookings()
                    ->with(['client.user', 'hotel', 'address'])
                    ->where('end_datetime', '>', now())
                    ->where('status', BookingStatus::Confirmed->value)
                    ->orderBy('start_datetime', 'asc')
                    ->get();

                $nextJob = $allUpcoming->first();
                $upcomingJobs = $allUpcoming->slice(1, 2)->values();

                $newInvites = BookingCaregiverNotification::with(['booking.client.user', 'booking.hotel', 'booking.address'])
                    ->where('caregiver_id', $caregiver->id)
                    ->whereNull('responded_at')
                    ->whereHas('booking', function ($query) {
                        $query->where('end_datetime', '>', now())
                            ->whereIn('status', [BookingStatus::Received->value, BookingStatus::Pending->value]);
                    })
                    ->orderBy('created_at', 'desc')
                    ->limit(3)
                    ->get()
                    ->pluck('booking');

                $caregiverData = [
                    'id' => $caregiver->id,
                    'firstName' => $caregiver->first_name,
                    'lastName' => $caregiver->last_name,
                    'rating' => $caregiver->rating,
                    'status' => $caregiver->status?->label() ?? null,
                    'availabilities' => $availabilities,
                    'bookingStatuses' => $bookingStatuses,
                    'nextJob' => $nextJob,
                    'upcomingJobs' => $upcomingJobs,
                    'newInvites' => $newInvites,
                    'timeSlots' => array_map(
                        fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                        TimeSlot::cases()
                    ),
                ];
            }
        }

        if ($user->isClient()) {
            $client = $user->client;

            if ($client) {
                $stats = [
                    'active_bookings' => $client->bookings()
                        ->where('end_datetime', '>', now())
                        ->where('status', '!=', BookingStatus::Cancelled->value)
                        ->count(),
                    'past_bookings' => $client->bookings()
                        ->whereIn('status', [BookingStatus::Completed->value, BookingStatus::Paid->value])
                        ->count(),
                    'favorite_caregivers' => $client->favoriteCaregivers()->count(),
                ];

                $allUpcoming = $client->bookings()
                    ->with(['caregiver.user'])
                    ->where('end_datetime', '>', now())
                    ->where('status', '!=', BookingStatus::Cancelled->value)
                    ->orderBy('start_datetime', 'asc')
                    ->get();

                $nextBooking = $allUpcoming->first();
                $upcomingBookings = $allUpcoming->slice(1, 2)->values();

                $recentBookings = $client->bookings()
                    ->with(['caregiver.user'])
                    ->where('end_datetime', '<=', now())
                    ->where('status', '!=', BookingStatus::Cancelled->value)
                    ->orderBy('end_datetime', 'desc')
                    ->limit(3)
                    ->get();

                $clientData = [
                    'nextBooking' => $nextBooking,
                    'upcomingBookings' => $upcomingBookings,
                    'recentBookings' => $recentBookings,
                    'bookingStatuses' => $bookingStatuses,
                ];
            }
        }

        return Inertia::render('dashboard', [
            'user' => [
                'name' => $user->name,
                'role' => $user->role,
            ],
            'stats' => $stats,
            'caregiver' => $caregiverData,
            'client' => $clientData,
            'admin' => $adminData,
        ]);
    }
}
