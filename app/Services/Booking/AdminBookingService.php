<?php

namespace App\Services\Booking;

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Enums\SitterPreference;
use App\Enums\SpecialConsideration;
use App\Mail\BookingNotification;
use App\Models\AttributeDefinition;
use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Models\BookingGroup;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientChild;
use App\Models\ClientPet;
use App\Models\Hotel;
use App\Models\User;
use App\Services\Booking\Contracts\BookingServiceInterface;
use App\Services\CaregiverRecommendation\CaregiverRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminBookingService implements BookingServiceInterface
{
    public function __construct(
        protected CaregiverRecommendationService $recommendationService
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
            'caregiver.user',
            'address',
            'attributeDefinitions',
        ])
            ->whereBetween('start_datetime', [$startDate, $endDate])
            ->orderBy('start_datetime');

        if ($status) {
            $query->where('status', $status);
        }

        $bookings = $query->get();

        $clients = Client::with('user')->get()->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->first_name.' '.$c->last_name,
            'email' => $c->user->email,
        ]);

        $hotels = Hotel::where('is_active', true)->get()->map(fn ($h) => [
            'id' => $h->id,
            'name' => $h->name,
            'city' => $h->city,
            'line1' => $h->line1,
            'line2' => $h->line2,
            'state' => $h->state,
            'zip' => $h->zip,
        ]);

        $caregivers = Caregiver::with('user')
            ->whereHas('status', fn ($q) => $q->where('is_active', true))
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->first_name.' '.$c->last_name,
            ]);

        $sitterPreferences = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            SitterPreference::cases(),
        );

        return Inertia::render('admin/bookings/index', [
            'bookings' => $bookings,
            'filters' => [
                'month' => (int) $month,
                'year' => (int) $year,
                'status' => $status,
            ],
            'clients' => $clients,
            'hotels' => $hotels,
            'caregivers' => $caregivers,
            'service_types' => array_map(
                fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                ServiceType::cases()
            ),
            'location_types' => array_map(
                fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                LocationType::cases()
            ),
            'booking_statuses' => array_map(
                fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->label(),
                    'colors' => $case->colors(),
                ],
                BookingStatus::cases()
            ),
            'payment_statuses' => array_map(
                fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                BookingPaymentStatus::cases()
            ),
            'special_consideration_options' => array_map(
                fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                SpecialConsideration::cases(),
            ),
            'booking_attributes' => AttributeDefinition::active()
                ->forBookings()
                ->get()
                ->map(fn ($attr) => [
                    'id' => $attr->id,
                    'name' => $attr->name,
                    'slug' => $attr->slug,
                    'type' => $attr->type,
                    'options' => $attr->options,
                ]),
            'sitter_preferences' => $sitterPreferences,
        ]);
    }

    public function show(Request $request, Booking $booking)
    {
        return redirect()->route('bookings.index');
    }

    public function store(Request $request)
    {
        $validated = $request->validated();

        // Validate minimum 4-hour duration
        $start = new \DateTime($validated['start_datetime']);
        $end = new \DateTime($validated['end_datetime']);
        $diffHours = $start->diff($end)->h + ($start->diff($end)->days * 24);
        if ($diffHours < 4) {
            throw ValidationException::withMessages([
                'end_datetime' => 'Booking must be at least 4 hours long.',
            ]);
        }

        $clientId = $validated['client_id'] ?? null;
        $addressId = $validated['address_id'] ?? null;

        // Create new client if new_client data provided
        if (! empty($validated['new_client']['first_name'])) {
            $user = User::create([
                'name' => $validated['new_client']['first_name'].' '.$validated['new_client']['last_name'],
                'email' => $validated['new_client']['email'],
                'password' => \Hash::make(\Str::random(16)),
                'role' => 'client',
            ]);

            $client = Client::create([
                'user_id' => $user->id,
                'first_name' => $validated['new_client']['first_name'],
                'last_name' => $validated['new_client']['last_name'],
                'phone' => $validated['new_client']['phone'] ?? null,
                'client_type' => $validated['new_client']['client_type'] ?? 'vacationer',
                'corporate_id' => $validated['corporate_id'] ?? null,
                'how_did_you_hear' => $validated['how_did_you_hear'] ?? null,
                'sitter_preferences' => $validated['sitter_preferences'] ?? null,
                'other_adults_present' => $validated['other_adults_present'] ?? null,
                'emergency_instructions' => $validated['emergency_instructions'] ?? null,
            ]);

            $clientId = $client->id;

            // Create client address if private_home and new address provided
            if ($validated['location_type'] === 'private_home' &&
                empty($addressId) &&
                ! empty($validated['address_line1'])) {
                $clientAddress = ClientAddress::create([
                    'client_id' => $client->id,
                    'line1' => $validated['address_line1'],
                    'line2' => $validated['address_line2'] ?? null,
                    'city' => $validated['address_city'],
                    'state' => $validated['address_state'],
                    'zip' => $validated['address_zip'],
                ]);
                $addressId = $clientAddress->id;
            }
        }

        // Get client data for snapshot
        $client = Client::with(['children', 'pets', 'user'])->find($clientId);

        // Prepare children snapshot (existing + new)
        $childrenSnapshot = ($client?->children ?? collect())->map(fn ($child) => [
            'name' => $child->name,
            'gender' => $child->gender,
            'birth_month' => $child->birth_month,
            'birth_year' => $child->birth_year,
        ])->toArray();

        if (! empty($validated['new_children'])) {
            foreach ($validated['new_children'] as $childData) {
                if (! empty($childData['name'])) {
                    $childrenSnapshot[] = [
                        'name' => $childData['name'],
                        'gender' => $childData['gender'] ?? null,
                        'birth_month' => isset($childData['birth_month']) ? (int) $childData['birth_month'] : null,
                        'birth_year' => isset($childData['birth_year']) ? (int) $childData['birth_year'] : null,
                    ];
                }
            }
        }

        // Prepare pets snapshot (existing + new)
        $petsSnapshot = ($client?->pets ?? collect())->map(fn ($pet) => [
            'name' => $pet->name,
            'type' => $pet->type,
            'breed' => $pet->breed,
            'notes' => $pet->notes,
        ])->toArray();

        if (! empty($validated['new_pets'])) {
            foreach ($validated['new_pets'] as $petData) {
                if (! empty($petData['name'])) {
                    $petsSnapshot[] = [
                        'name' => $petData['name'],
                        'type' => $petData['type'] ?? null,
                        'breed' => $petData['breed'] ?? null,
                        'notes' => $petData['notes'] ?? null,
                    ];
                }
            }
        }

        // Handle saving new children/pets to client profile if requested
        if (! empty($validated['new_children']) && ($validated['save_children_pets_to_profile'] ?? true) && $clientId) {
            $this->saveNewChildren($clientId, $validated['new_children']);
        }

        if (! empty($validated['new_pets']) && ($validated['save_children_pets_to_profile'] ?? true) && $clientId) {
            $this->saveNewPets($clientId, $validated['new_pets']);
        }

        $bookingGroup = BookingGroup::create([
            'client_id' => $clientId,
            'submitted_at' => now(),
            'submission_type' => 'admin',
            'is_split' => false,
        ]);

        Booking::create([
            'booking_group_id' => $bookingGroup->id,
            'client_id' => $clientId,
            'caregiver_id' => $validated['caregiver_id'] ?? null,
            'availability_id' => null,
            'hotel_id' => $validated['hotel_id'] ?? null,
            'address_id' => $addressId,
            'address_line1' => $validated['address_line1'] ?? null,
            'address_line2' => $validated['address_line2'] ?? null,
            'address_city' => $validated['address_city'] ?? null,
            'address_state' => $validated['address_state'] ?? null,
            'address_zip' => $validated['address_zip'] ?? null,
            'client_first_name' => $client?->first_name,
            'client_last_name' => $client?->last_name,
            'client_phone' => $client?->phone,
            'client_email' => $client?->user?->email,
            'children' => $childrenSnapshot,
            'pets' => $petsSnapshot,
            'service_type' => $validated['service_type'],
            'location_type' => $validated['location_type'],
            'rental_platform' => $validated['rental_platform'] ?? null,
            'start_datetime' => $validated['start_datetime'],
            'end_datetime' => $validated['end_datetime'],
            'status' => $validated['status'],
            'special_considerations' => $validated['special_considerations'] ?? null,
            'caregiver_notes' => $validated['caregiver_notes'] ?? null,
            'notes_to_sitterwise' => $validated['notes_to_sitterwise'] ?? null,
            'admin_notes' => $validated['admin_notes'] ?? null,
            'corporate_id' => $validated['corporate_id'] ?? null,
            'sitter_preferences' => $validated['sitter_preferences'] ?? null,
            'other_adults_present' => $validated['other_adults_present'] ?? null,
            'special_needs_notes' => $validated['special_needs_notes'] ?? null,
            'emergency_instructions' => $validated['emergency_instructions'] ?? null,
            'total_amount' => 0,
            'payment_status' => $validated['payment_status'],
            'requires_payment' => $validated['requires_payment'] ?? true,
        ]);

        return redirect()->route('bookings.index')->with('success', 'Booking created successfully.');
    }

    public function update(Request $request, Booking $booking)
    {
        $validated = $request->validated();

        // Validate minimum 4-hour duration
        $start = new \DateTime($validated['start_datetime']);
        $end = new \DateTime($validated['end_datetime']);
        $diffHours = $start->diff($end)->h + ($start->diff($end)->days * 24);
        if ($diffHours < 4) {
            throw ValidationException::withMessages([
                'end_datetime' => 'Booking must be at least 4 hours long.',
            ]);
        }

        // Handle deleted children
        if (! empty($validated['deleted_child_ids'])) {
            ClientChild::whereIn('id', $validated['deleted_child_ids'])->delete();
        }

        // Handle deleted pets
        if (! empty($validated['deleted_pet_ids'])) {
            ClientPet::whereIn('id', $validated['deleted_pet_ids'])->delete();
        }

        // Create new client address if private_home and new address provided
        $addressId = $validated['address_id'] ?? null;
        if (
            ($validated['location_type'] ?? null) === 'private_home' &&
            empty($addressId) &&
            ! empty($validated['address_line1'])
        ) {
            $clientAddress = ClientAddress::create([
                'client_id' => $booking->client_id,
                'line1' => $validated['address_line1'],
                'line2' => $validated['address_line2'] ?? null,
                'city' => $validated['address_city'],
                'state' => $validated['address_state'],
                'zip' => $validated['address_zip'],
            ]);
            $addressId = $clientAddress->id;
        }

        // Update client snapshot data
        $client = Client::with(['children', 'pets', 'user'])->find($booking->client_id);

        // Prepare children snapshot (existing + new)
        // If new_children is provided, start from client's saved children
        // Otherwise, retain the existing booking's children (in case they weren't saved to profile)
        if (! empty($validated['new_children'])) {
            $childrenSnapshot = ($client?->children ?? collect())->map(fn ($child) => [
                'name' => $child->name,
                'gender' => $child->gender,
                'birth_month' => $child->birth_month,
                'birth_year' => $child->birth_year,
            ])->toArray();

            foreach ($validated['new_children'] as $childData) {
                if (! empty($childData['name'])) {
                    $childrenSnapshot[] = [
                        'name' => $childData['name'],
                        'gender' => $childData['gender'] ?? null,
                        'birth_month' => isset($childData['birth_month']) ? (int) $childData['birth_month'] : null,
                        'birth_year' => isset($childData['birth_year']) ? (int) $childData['birth_year'] : null,
                    ];
                }
            }
        } else {
            // No new children provided - retain existing booking's children snapshot
            $childrenSnapshot = $booking->children ?? [];
        }

        // Prepare pets snapshot (existing + new)
        // If new_pets is provided, start from client's saved pets
        // Otherwise, retain the existing booking's pets (in case they weren't saved to profile)
        if (! empty($validated['new_pets'])) {
            $petsSnapshot = ($client?->pets ?? collect())->map(fn ($pet) => [
                'name' => $pet->name,
                'type' => $pet->type,
                'breed' => $pet->breed,
                'notes' => $pet->notes,
            ])->toArray();

            foreach ($validated['new_pets'] as $petData) {
                if (! empty($petData['name'])) {
                    $petsSnapshot[] = [
                        'name' => $petData['name'],
                        'type' => $petData['type'] ?? null,
                        'breed' => $petData['breed'] ?? null,
                        'notes' => $petData['notes'] ?? null,
                    ];
                }
            }
        } else {
            // No new pets provided - retain existing booking's pets snapshot
            $petsSnapshot = $booking->pets ?? [];
        }

        // Handle saving new children/pets to client profile if requested
        if (! empty($validated['new_children']) && ($validated['save_children_pets_to_profile'] ?? true) && $booking->client_id) {
            $this->saveNewChildren($booking->client_id, $validated['new_children']);
        }

        if (! empty($validated['new_pets']) && ($validated['save_children_pets_to_profile'] ?? true) && $booking->client_id) {
            $this->saveNewPets($booking->client_id, $validated['new_pets']);
        }

        $updateData = [
            ...$validated,
            'address_id' => $addressId,
            'client_first_name' => $client?->first_name,
            'client_last_name' => $client?->last_name,
            'client_phone' => $client?->phone,
            'client_email' => $client?->user?->email,
            'children' => $childrenSnapshot,
            'pets' => $petsSnapshot,
        ];

        $booking->update($updateData);

        return redirect()->route('bookings.index')->with('success', 'Booking updated successfully.');
    }

    /**
     * Save new children to client profile.
     */
    protected function saveNewChildren(int $clientId, array $children): void
    {
        foreach ($children as $childData) {
            ClientChild::create([
                'client_id' => $clientId,
                'name' => $childData['name'] ?? null,
                'gender' => $childData['gender'] ?? null,
                'birth_month' => isset($childData['birth_month']) ? (int) $childData['birth_month'] : null,
                'birth_year' => isset($childData['birth_year']) ? (int) $childData['birth_year'] : null,
            ]);
        }
    }

    /**
     * Save new pets to client profile.
     */
    protected function saveNewPets(int $clientId, array $pets): void
    {
        foreach ($pets as $petData) {
            ClientPet::create([
                'client_id' => $clientId,
                'name' => $petData['name'] ?? null,
                'type' => $petData['type'] ?? null,
                'breed' => $petData['breed'] ?? null,
                'notes' => $petData['notes'] ?? null,
            ]);
        }
    }

    public function destroy(Booking $booking)
    {
        $booking->bookingGroup->delete();
        $booking->delete();

        return redirect()->route('bookings.index')->with('success', 'Booking deleted successfully.');
    }

    public function notify(Request $request, Booking $booking)
    {
        try {
            $validated = $request->validate([
                'caregiver_ids' => 'required|array',
                'caregiver_ids.*' => 'exists:caregivers,id',
            ]);

            $caregivers = Caregiver::whereIn('id', $validated['caregiver_ids'])->get();

            // Create notification records for each caregiver
            foreach ($caregivers as $caregiver) {
                BookingCaregiverNotification::updateOrCreate(
                    [
                        'booking_id' => $booking->id,
                        'caregiver_id' => $caregiver->id,
                    ],
                    [
                        'notified_at' => now(),
                        'viewed_at' => null,
                        'responded_at' => null,
                        'claimed' => false,
                    ]
                );

                // Send email notification
                if ($caregiver->user && $caregiver->user->email) {
                    Mail::to($caregiver->user->email)
                        ->send(new BookingNotification($booking, $caregiver));
                }
            }

            return back()->with('success', 'Caregivers have been notified.');
        } catch (\Exception $e) {
            \Log::error('Booking notify error: '.$e->getMessage());

            return back()->withErrors(['error' => 'Failed to notify caregivers: '.$e->getMessage()]);
        }
    }

    public function recommendedCaregivers(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
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
            'reimbursement' => 'nullable|numeric',
            'reimbursement_description' => 'nullable|string',
            'tip' => 'nullable|numeric',
            'bonus' => 'nullable|numeric',
        ]);

        // TODO: Call Stripe API to process payment
        // Placeholder for Stripe integration
        // Stripe::charges()->create([...])

        // Update booking with payment-related fields
        $booking->update([
            'reimbursement' => $validated['reimbursement'] ?? null,
            'reimbursement_description' => $validated['reimbursement_description'] ?? null,
            'tip' => $validated['tip'] ?? null,
            'bonus' => $validated['bonus'] ?? null,
            // Need to determine payment status based on Stripe response
            'payment_status' => BookingPaymentStatus::Paid->value, // Let's just set to Paid for now
            'status' => BookingStatus::Paid->value,
        ]);

        return redirect()->back()->with('success', 'Payment processed successfully.');
    }
}
