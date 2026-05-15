<?php

namespace App\Http\Controllers;

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
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
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Hotel;
use App\Models\QuickLink;
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
            $stats = [
                'totalCaregivers' => Caregiver::count(),
                'activeCaregivers' => Caregiver::whereHas('status', function ($query) {
                    $query->where('name', 'Active');
                })->count(),
                'totalClients' => User::where('role', 'client')->count(),
                'totalBookings' => Booking::count(),
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
            ];
        }

        if ($user->isCaregiver()) {
            $caregiver = $user->caregiver;
            $user->load(['caregiver.status']);

            if ($caregiver) {
                $availabilities = $caregiver->availabilities()
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
                    'status' => $caregiver->status ? [
                        'name' => $caregiver->status->name,
                    ] : null,
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
