<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Models\Caregiver;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $stats = [];
        $caregiverData = null;
        $clientData = null;

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            $stats = [
                'total_caregivers' => Caregiver::count(),
                'active_caregivers' => Caregiver::whereHas('status', function ($query) {
                    $query->where('name', 'Active');
                })->count(),
                'total_clients' => User::where('role', 'client')->count(),
                'total_bookings' => Booking::count(),
            ];

            $bookingStatuses = array_map(
                fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->label(),
                    'colors' => $case->colors(),
                ],
                BookingStatus::cases()
            );

            $adminData = [
                'bookings_needing_attention' => Booking::with(['client.user'])
                    ->whereNull('caregiver_id')
                    ->whereIn('status', [BookingStatus::Received->value, BookingStatus::Pending->value])
                    ->inFuture()
                    ->orderBy('start_datetime', 'asc')
                    ->limit(5)
                    ->get(),
                'todays_bookings' => Booking::with(['client.user', 'caregiver.user'])
                    ->inToday()
                    ->orderBy('start_datetime', 'asc')
                    ->get(),
                'recent_bookings' => Booking::with(['client.user'])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(),
                'recent_caregivers' => Caregiver::with('user')
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(),
                'booking_statuses' => $bookingStatuses,
            ];
        }

        if ($user->isCaregiver()) {
            $caregiver = $user->caregiver;
            $user->load(['caregiver.status']);

            if ($caregiver) {
                $availabilities = $caregiver->availabilities()
                    ->inTheFuture()
                    ->orderBy('date')
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
                    'first_name' => $caregiver->first_name,
                    'last_name' => $caregiver->last_name,
                    'rating' => $caregiver->rating,
                    'status' => $caregiver->status ? [
                        'name' => $caregiver->status->name,
                    ] : null,
                    'availabilities' => $availabilities,
                    'next_job' => $nextJob,
                    'upcoming_jobs' => $upcomingJobs,
                    'new_invites' => $newInvites,
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
                    'next_booking' => $nextBooking,
                    'upcoming_bookings' => $upcomingBookings,
                    'recent_bookings' => $recentBookings,
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
            'admin' => $adminData ?? null,
        ]);
    }
}
