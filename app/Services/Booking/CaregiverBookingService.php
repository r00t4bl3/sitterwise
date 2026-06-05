<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Enums\SpecialConsideration;
use App\Events\BookingAccepted;
use App\Events\JobConfirmed;
use App\Events\JobReleased;
use App\Events\JobReserved;
use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Services\Booking\Contracts\BookingServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CaregiverBookingService implements BookingServiceInterface, HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('caregiver', except: ['destroy']),
        ];
    }

    /**
     * Show caregiver's available bookings page (Inertia).
     */
    public function index(Request $request)
    {
        $caregiver = $request->user()->caregiver;

        $bookings = $caregiver->bookingNotifications()
            ->with(['booking.bookingGroup.bookings', 'booking.client', 'booking.client.user'])
            ->where('claimed', false)
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 'confirmed');
            })
            ->paginate(5)
            ->through(function ($notification) {
                $booking = $notification->booking;
                $group = $booking->bookingGroup;

                // Lazy expiration check
                if ($booking->reservation_expires_at && now()->gt($booking->reservation_expires_at)) {
                    $booking->update([
                        'reserved_by' => null,
                        'reservation_expires_at' => null,
                        'status' => 'received',
                    ]);
                }

                $siblings = $group
                    ? $group->bookings->where('id', '!=', $booking->id)->values()
                    : collect();

                return [
                    'id' => $booking->id,
                    'ulid' => $booking->ulid,
                    'booking_group_id' => $group?->id,
                    'group_size' => $group ? $group->bookings->count() : 1,
                    'client_name' => $booking->client->first_name.' '.$booking->client->last_name,
                    'start_datetime' => $booking->start_datetime,
                    'end_datetime' => $booking->end_datetime,
                    'status' => $booking->status,
                    'reserved_by' => $booking->reserved_by,
                    'reservation_expires_at' => $booking->reservation_expires_at,
                    'notified_at' => $notification->notified_at,
                    'viewed_at' => $notification->viewed_at,
                    'sibling_dates' => $siblings->map(fn ($s) => [
                        'id' => $s->id,
                        'ulid' => $s->ulid,
                        'start_datetime' => $s->start_datetime,
                        'end_datetime' => $s->end_datetime,
                        'status' => $s->status,
                    ]),
                ];
            });

        return Inertia::render('caregiver/bookings/index', [
            'bookings' => $bookings,
        ]);
    }

    /**
     * Show a specific booking detail page (Inertia).
     */
    public function show(Request $request, Booking $booking)
    {
        $caregiver = $request->user()->caregiver;

        // Check if caregiver was notified for this booking
        $notification = BookingCaregiverNotification::where('booking_id', $booking->id)
            ->where('caregiver_id', $caregiver->id)
            ->first();

        if (! $notification) {
            abort(403, 'You were not notified for this booking');
        }

        // Mark as viewed
        if (! $notification->viewed_at) {
            $notification->update(['viewed_at' => now()]);
        }

        // Get booking details
        $booking->load('bookingGroup.bookings', 'client.user');

        if (! $booking) {
            abort(404);
        }

        // Check if booking is already confirmed
        if ($booking->status === 'confirmed') {
            return redirect()->route('bookings.index')
                ->with('info', 'This booking has already been confirmed.');
        }

        // Lazy expiration check
        if ($booking->reservation_expires_at && now()->gt($booking->reservation_expires_at)) {
            $booking->update([
                'reserved_by' => null,
                'reservation_expires_at' => null,
                'status' => 'received',
            ]);
        }

        $group = $booking->bookingGroup;
        $siblingBookings = $group?->bookings
            ->filter(fn ($b) => $b->id !== $booking->id)
            ->values()
            ->map(fn ($b) => [
                'id' => $b->id,
                'ulid' => $b->ulid,
                'start_datetime' => $b->start_datetime,
                'end_datetime' => $b->end_datetime,
                'status' => $b->status,
            ]);

        $bookingStatuses = array_map(
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'colors' => $case->colors(),
            ],
            BookingStatus::cases()
        );

        return Inertia::render('caregiver/bookings/show', [
            'booking_statuses' => $bookingStatuses,
            'booking' => [
                'id' => $booking->id,
                'ulid' => $booking->ulid,
                'service_type' => ServiceType::tryFrom($booking->service_type)?->label() ?? $booking->service_type,
                'client_name' => $booking->client->first_name.' '.$booking->client->last_name,
                'client_phone' => $booking->client_phone ?? $booking->client->user?->phone,
                'client_email' => $booking->client_email ?? $booking->client->user?->email,
                'address_line1' => $booking->address_line1,
                'address_line2' => $booking->address_line2,
                'address_city' => $booking->address_city,
                'address_state' => $booking->address_state,
                'address_zip' => $booking->address_zip,
                'hotel_id' => $booking->hotel_id,
                'hotel_name' => $booking->bookingGroup->hotel_name ?? $booking->hotel?->name,
                'location_type' => $booking->location_type,
                'start_datetime' => $booking->start_datetime,
                'end_datetime' => $booking->end_datetime,
                'status' => $booking->status,
                'special_considerations' => collect($booking->special_considerations)
                    ->map(fn ($sc) => SpecialConsideration::tryFrom($sc)?->label() ?? $sc)
                    ->toArray(),
                'caregiver_notes' => $booking->caregiver_notes,
                'reserved_by' => $booking->reserved_by,
                'reservation_expires_at' => $booking->reservation_expires_at,
                'notified_at' => $notification->notified_at,
                'viewed_at' => $notification->viewed_at,
                'children' => $booking->children,
                'children_notes' => $booking->children_notes,
                'pets' => $booking->pets,
                'booking_group' => $group ? [
                    'id' => $group->id,
                    'bookings_count' => $group->bookings->count(),
                    'sibling_bookings' => $siblingBookings,
                ] : null,
            ],
        ]);
    }

    public function store(Request $request)
    {
        abort(403, 'Caregivers cannot update bookings');
    }

    public function update(Request $request, Booking $booking)
    {
        abort(403, 'Caregivers cannot update bookings');
    }

    public function destroy(Booking $booking)
    {
        abort(403, 'Caregivers cannot delete bookings');
    }

    /**
     * Reserve a booking (atomic operation, group-aware).
     */
    public function reserve(Request $request, Booking $booking)
    {
        $caregiver = $request->user()->caregiver;

        $notification = BookingCaregiverNotification::where('booking_id', $booking->id)
            ->where('caregiver_id', $caregiver->id)
            ->first();

        if (! $notification) {
            return back()->with('error', 'You were not notified for this booking');
        }

        if (! $notification->viewed_at) {
            $notification->update(['viewed_at' => now()]);
        }

        $expiresIn = 60;
        $expiresAt = now()->addSeconds($expiresIn);

        return $this->withSiblingLock($booking, function ($bookings) use ($caregiver, $expiresIn, $expiresAt) {
            $siblingIds = $bookings->pluck('id')->toArray();

            $updated = DB::table('bookings')
                ->whereIn('id', $siblingIds)
                ->whereIn('status', ['received', 'reserved'])
                ->where(function ($query) {
                    $query->whereNull('reserved_by')
                        ->orWhere('reservation_expires_at', '<', now());
                })
                ->update([
                    'reserved_by' => $caregiver->id,
                    'reservation_expires_at' => $expiresAt,
                    'status' => 'reserved',
                ]);

            if ($updated !== count($siblingIds)) {
                return back()->with('error', 'This booking is no longer available — it may have been reserved by another caregiver.');
            }

            foreach ($siblingIds as $id) {
                broadcast(new JobReserved($id, $caregiver->id, $expiresIn))->toOthers();
            }

            return back()->with('expires_in', $expiresIn);
        });
    }

    /**
     * Confirm a reserved booking (atomic operation, group-aware).
     */
    public function confirm(Request $request, Booking $booking)
    {
        $caregiver = $request->user()->caregiver;

        return $this->withSiblingLock($booking, function ($bookings) use ($booking, $caregiver) {
            $siblingIds = $bookings->pluck('id')->toArray();

            $updated = DB::table('bookings')
                ->whereIn('id', $siblingIds)
                ->where('reserved_by', $caregiver->id)
                ->where('reservation_expires_at', '>', now())
                ->update([
                    'status' => 'confirmed',
                    'caregiver_id' => $caregiver->id,
                    'confirmed_by' => $caregiver->id,
                    'confirmed_at' => now(),
                    'reserved_by' => null,
                    'reservation_expires_at' => null,
                ]);

            if ($updated !== count($siblingIds)) {
                return back()->with('error', 'Your reservation has expired. Please try reserving the booking again.');
            }

            BookingCaregiverNotification::whereIn('booking_id', $siblingIds)
                ->where('caregiver_id', $caregiver->id)
                ->update(['claimed' => true, 'responded_at' => now()]);

            foreach ($siblingIds as $id) {
                broadcast(new JobConfirmed($id, $caregiver->id))->toOthers();
            }

            event(new BookingAccepted($booking));

            return to_route('jobs.index')->with('success', 'Booking confirmed successfully');
        });
    }

    /**
     * Release a reservation (group-aware).
     */
    public function release(Request $request, Booking $booking)
    {
        $caregiver = $request->user()->caregiver;

        return $this->withSiblingLock($booking, function ($bookings) use ($caregiver) {
            $siblingIds = $bookings->pluck('id')->toArray();

            DB::table('bookings')
                ->whereIn('id', $siblingIds)
                ->where('reserved_by', $caregiver->id)
                ->update([
                    'reserved_by' => null,
                    'reservation_expires_at' => null,
                    'status' => 'received',
                ]);

            foreach ($siblingIds as $id) {
                broadcast(new JobReleased($id, $caregiver->id))->toOthers();
            }

            return back();
        });
    }

    /**
     * Execute a callback with all sibling bookings locked for update.
     *
     * For single-booking groups, passes a collection of just the booking
     * with no locking or transaction overhead.
     */
    private function withSiblingLock(Booking $booking, Closure $callback): mixed
    {
        if (! $booking->relationLoaded('bookingGroup')) {
            $booking->load('bookingGroup');
        }

        $group = $booking->bookingGroup;
        $isGroup = $group && $group->bookings()->count() > 1;

        if (! $isGroup) {
            return $callback(collect([$booking]));
        }

        return DB::transaction(function () use ($group, $callback) {
            $siblings = Booking::where('booking_group_id', $group->id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->get();

            return $callback($siblings);
        });
    }

    public function processPayment(Request $request, Booking $booking)
    {
        abort(403, 'Caregivers cannot process payments');
    }
}
