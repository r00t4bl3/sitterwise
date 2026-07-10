<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentResolution;
use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Enums\SpecialConsideration;
use App\Http\Requests\RateBookingRequest;
use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Models\BookingRating;
use App\Models\Caregiver;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return redirect()->route('bookings.index');
        }

        $caregiver = $user->caregiver;

        if (! $caregiver) {
            abort(403, 'Caregiver profile not found');
        }

        $query = Booking::with(['client.user', 'hotel', 'address', 'caregiver', 'client', 'clientRating', 'caregiverRating', 'assignments', 'bookingGroup'])
            ->where('caregiver_id', $caregiver->id)
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Completed->value, BookingStatus::Paid->value]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $terms = array_filter(explode(' ', $search));
            $query->where(function ($q) use ($terms) {
                $q->whereHas('client', function ($cq) use ($terms) {
                    foreach ($terms as $term) {
                        $cq->where(function ($q) use ($term) {
                            $q->where('first_name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%");
                        });
                    }
                })
                    ->orWhereHas('hotel', fn ($q) => $q->where('name', 'like', '%'.implode(' ', $terms).'%'))
                    ->orWhereHas('bookingGroup', fn ($q) => $q->where('location_type', 'like', '%'.implode(' ', $terms).'%'));
            });
        }

        $bookings = $query->orderBy('start_datetime', 'desc')
            ->paginate(10)
            ->appends($request->query());

        $bookings->getCollection()->transform(fn ($booking) => [
            ...$booking->toArray(),
            'assignment_id' => $booking->assignments->first()?->id,
            'assignment_resolution' => $booking->assignments->first()?->resolution,
            'client_name' => $booking->client?->full_name,
        ]);

        $bookingStatuses = array_map(
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'colors' => $case->colors(),
            ],
            BookingStatus::cases()
        );

        $serviceTypes = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            ServiceType::cases()
        );

        $locationTypes = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            LocationType::cases()
        );

        return Inertia::render('caregiver/jobs/index', [
            'jobs' => $bookings,
            'booking_statuses' => $bookingStatuses,
            'service_types' => $serviceTypes,
            'location_types' => $locationTypes,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
            ],
        ]);
    }

    public function show(Request $request, Booking $booking)
    {
        $user = $request->user();

        // Admins have no caregiver profile; send them to the admin booking view
        // instead of a dead-end 403.
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return redirect()->route('bookings.show', $booking->ulid);
        }

        $caregiver = $user->caregiver;

        if (! $caregiver) {
            abort(403, 'Caregiver profile not found');
        }

        $isAssigned = $booking->caregiver_id === $caregiver->id;
        $isInvited = BookingCaregiverNotification::where('booking_id', $booking->id)
            ->where('caregiver_id', $caregiver->id)
            ->exists();

        if (! $isAssigned && ! $isInvited) {
            abort(403, 'You are not authorized to view this job');
        }

        if ($isInvited && ! $isAssigned && $booking->caregiver_id === null) {
            return redirect()->route('bookings.show', $booking->ulid);
        }

        // Invited, but the job was already claimed by another caregiver. Show a
        // friendly, PII-free page instead of leaking the client's details.
        if (! $isAssigned && $booking->caregiver_id !== null) {
            return Inertia::render('caregiver/jobs/filled');
        }

        $booking->load('bookingGroup', 'client.user', 'hotel', 'address', 'clientRating', 'caregiverRating');

        return Inertia::render('caregiver/jobs/show', [
            'booking' => [
                'id' => $booking->id,
                'ulid' => $booking->ulid,
                // The Care.com "Corporate Job #" (null for non-corporate bookings).
                // Surfaced so caregivers copy this number onto the mileage form
                // instead of our internal job id (#307-adjacent request).
                'corporate_id' => $booking->corporate_id,
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
                'children' => $booking->children,
                'children_notes' => $booking->children_notes,
                'pets' => $booking->pets,
                'client_rating' => $booking->client_rating,
                'caregiver_rating' => $booking->caregiver_rating,
                'total_working_hour' => (float) ($booking->total_working_hour ?? 0),
                'paid_to_caregiver_hourly' => (float) ($booking->paid_to_caregiver_hourly ?? 0),
                'paid_to_caregiver' => (float) ($booking->paid_to_caregiver ?? 0),
                'reimbursement' => (float) ($booking->reimbursement ?? 0),
                'reimbursement_description' => $booking->reimbursement_description,
                'bonus' => (float) ($booking->bonus ?? 0),
                'tip' => (float) ($booking->tip ?? 0),
                'paid_to_caregiver_total' => (float) ($booking->paid_to_caregiver_total ?? 0),
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

        // Recompute worked hours explicitly from the submitted times. The model
        // only recalculates hours when start/end change, so a caregiver who
        // confirms the pre-filled times without editing them would otherwise
        // leave a stale total_working_hour (0 for imported bookings) and the
        // job would complete at $0 and never be charged.
        $workingHours = Carbon::parse($validated['start_datetime'])
            ->diffInMinutes(Carbon::parse($validated['end_datetime'])) / 60;

        $booking->update([
            'checkout_at' => now(),
            'start_datetime' => $validated['start_datetime'],
            'end_datetime' => $validated['end_datetime'],
            'total_working_hour' => $workingHours,
            'reimbursement' => $validated['reimbursement'] ?? null,
            'reimbursement_description' => $validated['reimbursement_description'] ?? null,
            'bonus' => $validated['bonus'] ?? null,
            'status' => BookingStatus::Completed->value,
        ]);

        $booking->assignments()
            ->where('caregiver_id', $booking->caregiver_id)
            ->first()
            ?->resolve(AssignmentResolution::Completed);

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

        return back()->with('success', 'Rating submitted successfully');
    }
}
