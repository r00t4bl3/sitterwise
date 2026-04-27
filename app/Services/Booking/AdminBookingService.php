<?php

namespace App\Services\Booking;

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Enums\SitterPreference;
use App\Enums\SpecialConsideration;
use App\Events\BookingCreated;
use App\Events\BookingInvitationSent;
use App\Models\AttributeDefinition;
use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Models\BookingGroup;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ClientChild;
use App\Models\ClientPet;
use App\Models\Hotel;
use App\Models\User;
use App\Services\Billing\JobBillingService;
use App\Services\Booking\Contracts\BookingServiceInterface;
use App\Services\CaregiverRecommendation\CaregiverRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AdminBookingService implements BookingServiceInterface
{
    public function __construct(
        protected CaregiverRecommendationService $recommendationService,
        protected JobBillingService $billingService
    ) {}

    public function index(Request $request)
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);
        $status = $request->input('status');

        $startDate = now()->year($year)->month($month)->startOfMonth();
        $endDate = $startDate->endOfMonth();

        $query = Booking::with([
            'client.user',
            'client.children',
            'client.pets',
            'hotel',
            'address',
            'caregiver.user',
            'caregiverNotifications',
        ]);

        if ($status) {
            $query->where('status', $status);
        }

        $bookings = $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_datetime', [$startDate, $endDate])
                ->orWhereBetween('end_datetime', [$startDate, $endDate]);
        })
            ->orderBy('start_datetime', 'asc')
            ->get();

        $serviceTypes = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            ServiceType::cases()
        );

        $locationTypes = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            LocationType::cases()
        );

        $specialConsiderations = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            SpecialConsideration::cases()
        );

        $bookingStatuses = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            BookingStatus::cases()
        );

        $paymentStatuses = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            BookingPaymentStatus::cases()
        );

        $sitterPreferenceOptions = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            SitterPreference::cases()
        );

        return Inertia::render('admin/bookings/index', [
            'bookings' => $bookings,
            'service_types' => $serviceTypes,
            'location_types' => $locationTypes,
            'special_consideration_options' => $specialConsiderations,
            'booking_statuses' => $bookingStatuses,
            'payment_statuses' => $paymentStatuses,
            'sitter_preference_options' => $sitterPreferenceOptions,
            'booking_attributes' => AttributeDefinition::where('entity_type', 'booking')->get(),
            'client_type_options' => [
                ['value' => 'resident', 'label' => 'SD Resident'],
                ['value' => 'vacationer', 'label' => 'Vacationer'],
            ],
            'filters' => [
                'month' => $month,
                'year' => $year,
                'status' => $status,
            ],
            'hotels' => Hotel::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required_without:new_client|exists:clients,id',
            'new_client' => 'required_without:client_id|array',
            'new_client.first_name' => 'required_with:new_client|string|max:255',
            'new_client.last_name' => 'required_with:new_client|string|max:255',
            'new_client.email' => 'required_with:new_client|email|max:255',
            'new_client.phone' => 'required_with:new_client|string|max:50',
            'new_client.client_type' => 'required_with:new_client|string',
            'service_type' => 'required|string',
            'location_type' => 'required|string',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'address_id' => 'nullable|exists:client_addresses,id',
            'address_line1' => 'required_without:address_id|string|max:500',
            'address_line2' => 'nullable|string|max:500',
            'address_city' => 'required_without:address_id|string|max:255',
            'address_state' => 'required_without:address_id|string|max:100',
            'address_zip' => 'required_without:address_id|string|max:20',
            'hotel_id' => 'nullable|exists:hotels,id',
            'rental_platform' => 'nullable|string|max:255',
            'special_considerations' => 'array',
            'caregiver_notes' => 'nullable|string',
            'notes_to_sitterwise' => 'nullable|string',
            'admin_notes' => 'nullable|string',
            'corporate_id' => 'nullable|string|max:255',
            'sitter_preferences' => 'array',
            'other_adults_present' => 'nullable|string|max:500',
            'emergency_instructions' => 'nullable|string',
            'special_needs_notes' => 'nullable|string',
            'how_did_you_hear' => 'nullable|string|max:255',
            'save_to_profile' => 'boolean',
            'new_children' => 'array',
            'new_pets' => 'array',
            'caregiver_id' => 'nullable|exists:caregivers,id',
            'status' => 'required|string',
            'payment_status' => 'required|string',
        ]);

        $client = null;
        if ($request->has('new_client')) {
            $user = User::create([
                'name' => $validated['new_client']['first_name'].' '.$validated['new_client']['last_name'],
                'email' => $validated['new_client']['email'],
                'password' => bcrypt(str()->random(16)),
                'role' => 'client',
            ]);

            $client = Client::create([
                'user_id' => $user->id,
                'first_name' => $validated['new_client']['first_name'],
                'last_name' => $validated['new_client']['last_name'],
                'phone' => $validated['new_client']['phone'],
                'client_type' => $validated['new_client']['client_type'],
            ]);
        } else {
            $client = Client::find($validated['client_id']);
        }

        if ($validated['save_to_profile']) {
            $client->update([
                'special_needs_notes' => $validated['special_needs_notes'],
                'emergency_instructions' => $validated['emergency_instructions'],
                'sitter_preferences' => $validated['sitter_preferences'],
                'other_adults_present' => $validated['other_adults_present'],
            ]);

            foreach ($validated['new_children'] as $child) {
                ClientChild::create([
                    'client_id' => $client->id,
                    'name' => $child['name'],
                    'gender' => $child['gender'],
                    'birth_month' => $child['birth_month'],
                    'birth_year' => $child['birth_year'],
                ]);
            }

            foreach ($validated['new_pets'] as $pet) {
                ClientPet::create([
                    'client_id' => $client->id,
                    'name' => $pet['name'],
                    'type' => $pet['type'],
                    'breed' => $pet['breed'],
                    'notes' => $pet['notes'],
                ]);
            }
        }

        $bookingGroup = BookingGroup::create([
            'client_id' => $client->id,
            'submitted_at' => now(),
            'submission_type' => 'admin',
        ]);

        $booking = Booking::create([
            'booking_group_id' => $bookingGroup->id,
            'client_id' => $client->id,
            'caregiver_id' => $validated['caregiver_id'] ?? null,
            'hotel_id' => $validated['hotel_id'],
            'address_id' => $validated['address_id'],
            'address_line1' => $validated['address_line1'],
            'address_line2' => $validated['address_line2'],
            'address_city' => $validated['address_city'],
            'address_state' => $validated['address_state'],
            'address_zip' => $validated['address_zip'],
            'service_type' => $validated['service_type'],
            'location_type' => $validated['location_type'],
            'rental_platform' => $validated['rental_platform'],
            'start_datetime' => $validated['start_datetime'],
            'end_datetime' => $validated['end_datetime'],
            'status' => $validated['status'],
            'payment_status' => $validated['payment_status'],
            'special_considerations' => $validated['special_considerations'],
            'caregiver_notes' => $validated['caregiver_notes'],
            'notes_to_sitterwise' => $validated['notes_to_sitterwise'],
            'admin_notes' => $validated['admin_notes'],
            'corporate_id' => $validated['corporate_id'],
            'sitter_preferences' => $validated['sitter_preferences'],
            'other_adults_present' => $validated['other_adults_present'],
            'special_needs_notes' => $validated['special_needs_notes'],
            'emergency_instructions' => $validated['emergency_instructions'],
            'how_did_you_hear' => $validated['how_did_you_hear'],
            'children' => $validated['new_children'],
            'pets' => $validated['new_pets'],
        ]);

        event(new BookingCreated($booking));

        return redirect()->back()->with('success', 'Booking created successfully.');
    }

    public function show(Request $request, Booking $booking)
    {
        $booking->load([
            'client.user',
            'client.children',
            'client.pets',
            'hotel',
            'address',
            'caregiver.user',
            'caregiverNotifications',
        ]);

        return response()->json($booking);
    }

    public function update(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'caregiver_id' => 'nullable|exists:caregivers,id',
            'hotel_id' => 'nullable|exists:hotels,id',
            'address_id' => 'nullable|exists:client_addresses,id',
            'address_line1' => 'required|string|max:500',
            'address_line2' => 'nullable|string|max:500',
            'address_city' => 'required|string|max:255',
            'address_state' => 'required|string|max:100',
            'address_zip' => 'required|string|max:20',
            'service_type' => 'required|string',
            'location_type' => 'required|string',
            'rental_platform' => 'nullable|string|max:255',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'status' => 'required|string',
            'payment_status' => 'required|string',
            'special_considerations' => 'array',
            'caregiver_notes' => 'nullable|string',
            'notes_to_sitterwise' => 'nullable|string',
            'admin_notes' => 'nullable|string',
            'corporate_id' => 'nullable|string|max:255',
            'sitter_preferences' => 'array',
            'other_adults_present' => 'nullable|string|max:500',
            'special_needs_notes' => 'nullable|string',
            'emergency_instructions' => 'nullable|string',
        ]);

        $booking->update($validated);

        return redirect()->back()->with('success', 'Booking updated successfully.');
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();

        return redirect()->back()->with('success', 'Booking deleted successfully.');
    }

    public function notify(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'caregiver_ids' => 'required|array',
            'caregiver_ids.*' => 'exists:caregivers,id',
        ]);

        foreach ($validated['caregiver_ids'] as $caregiverId) {
            $notification = BookingCaregiverNotification::firstOrCreate([
                'booking_id' => $booking->id,
                'caregiver_id' => $caregiverId,
            ], [
                'notified_at' => now(),
            ]);

            if ($notification->wasRecentlyCreated) {
                // Send notification email/sms
                event(new BookingInvitationSent($booking, Caregiver::find($caregiverId)));
            }
        }

        return redirect()->back()->with('success', 'Caregivers notified successfully.');
    }

    public function recommendedCaregivers(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required_without:new_client|exists:clients,id',
            'service_type' => 'nullable|string',
            'start_datetime' => 'nullable|date',
            'end_datetime' => 'nullable|date|after:start_datetime',
        ]);

        $client = Client::with('favoriteCaregivers')->find($validated['client_id']);

        // Create a mock booking if dates are provided
        $mockBooking = null;
        if (! empty($validated['service_type']) && ! empty($validated['start_datetime'])) {
            $mockBooking = new Booking;
            $mockBooking->service_type = $validated['service_type'];
            $mockBooking->start_datetime = $validated['start_datetime'];
            $mockBooking->end_datetime = $validated['end_datetime'];
        }

        $recommended = $this->recommendationService->getRecommendedCaregivers(
            $client,
            $mockBooking,
            20
        );

        return response()->json($recommended->map(function ($item) {
            return [
                'id' => $item['caregiver']->id,
                'name' => $item['caregiver']->first_name.' '.$item['caregiver']->last_name,
                'score' => $item['score'],
                'matchBadge' => $item['matchBadge'],
            ];
        }));
    }

    public function reserve(Request $request, Booking $booking)
    {
        abort(403, 'Admin cannot reserve bookings');
    }

    public function confirm(Request $request, Booking $booking)
    {
        abort(403, 'Admin cannot confirm bookings');
    }

    public function release(Request $request, Booking $booking)
    {
        abort(403, 'Admin cannot release bookings');
    }

    public function processPayment(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'total_working_hour' => 'nullable|numeric|min:0',
            'reimbursement' => 'nullable|numeric|min:0',
            'reimbursement_description' => 'nullable|string',
            'tip' => 'nullable|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
        ]);

        // 1. Update the booking with adjustments (this triggers calculateTotalAmount)
        Log::debug('AdminBookingService::processPayment', [
            'booking_id' => $booking->id,
            'exists' => $booking->exists,
            'input' => [
                'total_working_hour' => $validated['total_working_hour'] ?? $booking->total_working_hour ?? 0,
                'reimbursement' => $validated['reimbursement'] ?? 0,
                'reimbursement_description' => $validated['reimbursement_description'] ?? null,
                'tip' => $validated['tip'] ?? 0,
                'bonus' => $validated['bonus'] ?? 0,
            ],
        ]);

        $success = $booking->update([
            'total_working_hour' => $validated['total_working_hour'] ?? $booking->total_working_hour ?? 0,
            'reimbursement' => $validated['reimbursement'] ?? 0,
            'reimbursement_description' => $validated['reimbursement_description'] ?? null,
            'tip' => $validated['tip'] ?? 0,
            'bonus' => $validated['bonus'] ?? 0,
        ]);

        Log::debug('Update result', [
            'success' => $success,
            'reimbursement_after' => $booking->reimbursement,
            'fresh_reimbursement' => $booking->fresh()->reimbursement ?? 'N/A',
        ]);

        // 2. Execute the actual charge via Stripe
        $result = $this->billingService->charge($booking);

        if ($result['success']) {
            // Success: status moved to Paid in BillingService
            return redirect()->back()->with('success', 'Payment processed and charged successfully.');
        }

        // Failure: details were saved but Stripe declined/errored
        return redirect()->back()->with('error', 'Booking details saved, but payment failed: '.$result['message']);
    }
}
