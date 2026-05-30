<?php

namespace App\Services\Booking;

use App\Enums\ServiceType;
use App\Enums\SpecialConsideration;
use App\Events\BookingAccepted;
use App\Events\JobConfirmed;
use App\Events\JobReleased;
use App\Events\JobReserved;
use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Services\Booking\Contracts\BookingServiceInterface;
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
            ->with(['booking.client', 'booking.client.user'])
            ->where('claimed', false)
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 'confirmed');
            })
            ->paginate(5)
            ->through(function ($notification) {
                $booking = $notification->booking;

                // Lazy expiration check
                if ($booking->reservation_expires_at && now()->gt($booking->reservation_expires_at)) {
                    $booking->update([
                        'reserved_by' => null,
                        'reservation_expires_at' => null,
                        'status' => 'received',
                    ]);
                }

                return [
                    'id' => $booking->id,
                    'ulid' => $booking->ulid,
                    'client_name' => $booking->client->first_name.' '.$booking->client->last_name,
                    'start_datetime' => $booking->start_datetime,
                    'end_datetime' => $booking->end_datetime,
                    'status' => $booking->status,
                    'reserved_by' => $booking->reserved_by,
                    'reservation_expires_at' => $booking->reservation_expires_at,
                    'notified_at' => $notification->notified_at,
                    'viewed_at' => $notification->viewed_at,
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
        $booking->load('client.user');

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

        return Inertia::render('caregiver/bookings/show', [
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
                'hotel_name' => $booking->hotel_name ?? $booking->hotel?->name,
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
     * Reserve a booking (atomic operation).
     */
    public function reserve(Request $request, Booking $booking)
    {
        $caregiver = $request->user()->caregiver;

        // Check if caregiver was notified for this booking
        $notification = BookingCaregiverNotification::where('booking_id', $booking->id)
            ->where('caregiver_id', $caregiver->id)
            ->first();

        if (! $notification) {
            return back()->with('error', 'You were not notified for this booking');
        }

        // Mark as viewed
        if (! $notification->viewed_at) {
            $notification->update(['viewed_at' => now()]);
        }

        // Atomic reservation update
        $expiresIn = 60; // 1 minute TTL
        $expiresAt = now()->addSeconds($expiresIn);

        $updated = DB::table('bookings')
            ->where('id', $booking->id)
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

        if ($updated === 0) {
            return back()->with('error', 'Booking is no longer available');
        }

        broadcast(new JobReserved($booking->id, $caregiver->id, $expiresIn))->toOthers();

        return back()->with('expires_in', $expiresIn);
    }

    /**
     * Confirm a reserved booking (atomic operation).
     */
    public function confirm(Request $request, Booking $booking)
    {
        $caregiver = $request->user()->caregiver;

        // Atomic confirmation
        $updated = DB::table('bookings')
            ->where('id', $booking->id)
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

        if ($updated === 0) {
            return back()->with('error', 'Reservation expired or invalid');
        }

        // Mark notification as claimed
        BookingCaregiverNotification::where('booking_id', $booking->id)
            ->where('caregiver_id', $caregiver->id)
            ->update(['claimed' => true, 'responded_at' => now()]);

        broadcast(new JobConfirmed($booking->id, $caregiver->id))->toOthers();

        event(new BookingAccepted($booking));

        return to_route('jobs.index')->with('success', 'Booking confirmed successfully');
    }

    /**
     * Release a reservation.
     */
    public function release(Request $request, Booking $booking)
    {
        $caregiver = $request->user()->caregiver;

        // Atomic release
        DB::table('bookings')
            ->where('id', $booking->id)
            ->where('reserved_by', $caregiver->id)
            ->update([
                'reserved_by' => null,
                'reservation_expires_at' => null,
                'status' => 'received',
            ]);

        broadcast(new JobReleased($booking->id, $caregiver->id))->toOthers();

        return back();
    }

    public function processPayment(Request $request, Booking $booking)
    {
        abort(403, 'Caregivers cannot process payments');
    }
}
