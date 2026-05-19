<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Enums\SpecialConsideration;
use App\Http\Requests\RateBookingRequest;
use App\Models\Booking;
use App\Models\BookingRating;
use App\Models\Caregiver;
use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $caregiver = $request->user()->caregiver;

        if (! $caregiver) {
            abort(403, 'Caregiver profile not found');
        }

        $bookings = Booking::with(['client.user', 'hotel', 'address', 'caregiver', 'client', 'clientRating', 'caregiverRating'])
            ->where('caregiver_id', $caregiver->id)
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Completed->value])
            ->orderBy('start_datetime', 'desc')
            ->paginate(10);

        $bookingStatuses = array_map(
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'colors' => $case->colors(),
            ],
            BookingStatus::cases()
        );

        return Inertia::render('caregiver/jobs/index', [
            'jobs' => $bookings,
            'booking_statuses' => $bookingStatuses,
        ]);
    }

    public function show(Request $request, Booking $booking)
    {
        $caregiver = $request->user()->caregiver;

        if (! $caregiver) {
            abort(403, 'Caregiver profile not found');
        }

        if ($booking->caregiver_id !== $caregiver->id) {
            abort(403, 'You are not authorized to view this job');
        }

        $booking->load('client.user', 'hotel', 'address', 'clientRating', 'caregiverRating');

        return Inertia::render('caregiver/jobs/show', [
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
                'hotel_name' => $booking->hotel?->name,
                'location_type' => $booking->location_type,
                'start_datetime' => $booking->start_datetime,
                'end_datetime' => $booking->end_datetime,
                'status' => $booking->status,
                'special_considerations' => collect($booking->special_considerations)
                    ->map(fn ($sc) => SpecialConsideration::tryFrom($sc)?->label() ?? $sc)
                    ->toArray(),
                'caregiver_notes' => $booking->caregiver_notes,
                'children' => $booking->children,
                'children_notes' => $booking->children_notes,
                'pets' => $booking->pets,
                'client_rating' => $booking->client_rating,
                'caregiver_rating' => $booking->caregiver_rating,
            ],
        ]);
    }

    public function checkout(Request $request, Booking $booking)
    {
        $caregiver = $request->user()->caregiver;

        if (! $caregiver) {
            abort(403, 'Caregiver profile not found');
        }

        if ($booking->caregiver_id !== $caregiver->id) {
            abort(403, 'You are not authorized to checkout this job');
        }

        $validated = $request->validate([
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'reimbursement' => 'nullable|numeric|min:0',
            'reimbursement_description' => 'nullable|string|max:255',
            'bonus' => 'nullable|numeric|min:0',
        ]);

        $booking->update([
            'checkout_at' => now(),
            'start_datetime' => $validated['start_datetime'],
            'end_datetime' => $validated['end_datetime'],
            'reimbursement' => $validated['reimbursement'] ?? null,
            'reimbursement_description' => $validated['reimbursement_description'] ?? null,
            'bonus' => $validated['bonus'] ?? null,
            'status' => BookingStatus::Completed->value,
        ]);

        return back()->with('success', 'Job updated successfully');
    }

    public function rate(RateBookingRequest $request, Booking $booking)
    {
        $user = $request->user();
        $type = $request->input('type');

        // Validate Authorization & Set Giver/Receiver
        if ($type === BookingRating::TYPE_CLIENT_TO_CAREGIVER) {
            if ($booking->client_id !== $user->client?->id) {
                abort(403, 'Unauthorized');
            }
            $raterId = $user->id;
            $ratableType = Caregiver::class;
            $ratableId = $booking->caregiver_id;
        } else {
            if ($booking->caregiver_id !== $user->caregiver?->id) {
                abort(403, 'Unauthorized');
            }
            $raterId = $user->id;
            $ratableType = Client::class;
            $ratableId = $booking->client_id;
        }

        // Only allow rating if job is completed or paid
        if (! in_array($booking->status, [BookingStatus::Completed->value, BookingStatus::Paid->value])) {
            abort(400, 'Only completed jobs can be rated.');
        }

        BookingRating::updateOrCreate(
            ['booking_id' => $booking->id, 'rater_id' => $raterId, 'ratable_type' => $ratableType, 'ratable_id' => $ratableId],
            [
                'rating' => $request->validated('rating'),
                'comment' => $request->validated('comment'),
            ]
        );

        // Trigger recalculation on the receiver's model
        if ($type === BookingRating::TYPE_CLIENT_TO_CAREGIVER) {
            $booking->caregiver->recalculateRating();
        } else {
            $booking->client->recalculateRating();
        }

        return back()->with('success', 'Rating submitted successfully');
    }
}
