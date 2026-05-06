<?php

namespace App\Services\Booking;

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Enums\SitterPreference;
use App\Enums\SpecialConsideration;
use App\Events\BookingAccepted;
use App\Events\BookingCreated;
use App\Events\BookingInvitationSent;
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
use App\Services\Billing\JobBillingService;
use App\Services\Booking\Contracts\BookingServiceInterface;
use App\Services\CaregiverRecommendation\CaregiverRecommendationService;
use Carbon\Carbon;
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
            ->get()
            ->map(function (Booking $booking) {
                if ($booking->client) {
                    $booking->client->setAttribute('children', $booking->client->children->map(fn ($c) => [
                        'name' => $c['name'],
                        'gender' => $c['gender'],
                        'birth_month' => $c['birth_month'],
                        'birth_year' => $c['birth_year'],
                    ]));
                    $booking->client->setAttribute('pets', $booking->client->pets->map(fn ($p) => [
                        // 'id' => $p->id,
                        'name' => $p['name'],
                        'type' => $p['type'],
                        'breed' => $p['breed'],
                        'notes' => $p['notes'],
                    ]));
                }

                return $booking;
            });

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
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'colors' => $case->colors(),
            ],
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

        $hotels = Hotel::where('is_active', true)->get()->map(fn ($h) => [
            'id' => $h->id,
            'name' => $h->name,
            'city' => $h->city,
            'line1' => $h->line1,
            'line2' => $h->line2,
            'state' => $h->state,
            'zip' => $h->zip,
        ]);

        $clients = Client::with('user')->get()->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->first_name.' '.$c->last_name,
            'email' => $c->user->email,
        ]);

        $caregivers = Caregiver::with('user')
            ->whereHas('status', fn ($q) => $q->where('is_active', true))
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->first_name.' '.$c->last_name,
            ]);

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
                'month' => (int) $month,
                'year' => (int) $year,
                'status' => $status,
            ],
            'hotels' => $hotels,
            'clients' => $clients,
            'caregivers' => $caregivers,

        ]);
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

        // Prepare children snapshot (filter profile by child_ids + add new_children)
        $childrenQuery = $client?->children ?? collect();
        if (array_key_exists('child_ids', $validated)) {
            $selectedChildIds = $validated['child_ids'] ?? [];
            $childrenQuery = $childrenQuery->filter(fn ($child) => in_array($child->id, $selectedChildIds));
        }

        $childrenSnapshot = $childrenQuery->map(fn ($child) => [
            'name' => $child->name,
            'gender' => $child->gender,
            'birth_month' => $child->birth_month,
            'birth_year' => $child->birth_year,
        ])->values()->toArray();

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

        // Prepare pets snapshot (filter profile by pet_ids + add new_pets)
        $petsQuery = $client?->pets ?? collect();
        if (array_key_exists('pet_ids', $validated)) {
            $selectedPetIds = $validated['pet_ids'] ?? [];
            $petsQuery = $petsQuery->filter(fn ($pet) => in_array($pet->id, $selectedPetIds));
        }

        $petsSnapshot = $petsQuery->map(fn ($pet) => [
            'name' => $pet->name,
            'type' => $pet->type,
            'breed' => $pet->breed,
            'notes' => $pet->notes,
        ])->values()->toArray();

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

        $booking = Booking::create([
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

        event(new BookingCreated($booking));

        if ($booking->caregiver_id) {
            event(new BookingAccepted($booking));
        }

        return redirect()->back()->with('success', 'Booking created successfully.');
    }

    public function show(Request $request, Booking $booking)
    {
        if ($request->wantsJson()) {
            $booking->load([
                'client.user',
                'client.children',
                'client.pets',
                'client.addresses',
                'client.favoriteCaregivers.user',
                'client.blockedCaregivers.user',
                'hotel',
                'address',
                'caregiver.user',
                'caregiverNotifications',
            ]);

            $booking->client->setRelation(
                'previousCaregivers',
                $booking->client->previousCaregivers()->with('user')->get()
            );

            return response()->json($booking);
        }

        return Inertia::render('admin/bookings/show', [
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
                'start_datetime' => $booking->start_datetime,
                'end_datetime' => $booking->end_datetime,
                'status' => $booking->status,
                'special_considerations' => collect($booking->special_considerations)
                    ->map(fn ($sc) => SpecialConsideration::tryFrom($sc)?->label() ?? $sc)
                    ->toArray(),
                'caregiver_notes' => $booking->caregiver_notes,
                'reserved_by' => $booking->reserved_by,
                'reservation_expires_at' => $booking->reservation_expires_at,
                'hotel_id' => $booking->hotel_id,
                'hotel_name' => $booking->hotel?->name,
                'location_type' => $booking->location_type,

                'children' => $booking->children,
                'pets' => $booking->pets,
            ],
        ]);
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

        // Build children snapshot directly from new_children input
        $childrenSnapshot = [];
        if ($request->has('new_children')) {
            // Only process if new_children is explicitly provided
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
        } else {
            // Keep existing children if new_children not provided
            $childrenSnapshot = $booking->children ?? [];
        }

        // Build pets snapshot directly from new_pets input
        $petsSnapshot = [];
        if ($request->has('new_pets')) {
            // Only process if new_pets is explicitly provided
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
        } else {
            // Keep existing pets if new_pets not provided
            $petsSnapshot = $booking->pets ?? [];
        }

        // Sync to client profile when checkbox is checked
        if (($validated['save_children_pets_to_profile'] ?? false) && $booking->client_id) {
            // Use childrenSnapshot (which may be existing or new)
            $this->syncClientChildren($booking->client_id, $childrenSnapshot);
            $this->syncClientPets($booking->client_id, $petsSnapshot);
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

        $oldCaregiverId = $booking->caregiver_id;
        $booking->update($updateData);

        if ($booking->caregiver_id && $booking->caregiver_id != $oldCaregiverId) {
            event(new BookingAccepted($booking));
        }

        return redirect()->back()->with('success', 'Booking updated successfully.');
    }

    /**
     * Sync children between booking snapshot and client profile.
     * Upsert based on name + birth_year match.
     * Delete profile children not in booking snapshot.
     */
    protected function syncClientChildren(int $clientId, array $childrenSnapshot): void
    {
        $existingChildren = ClientChild::where('client_id', $clientId)->get();

        // Build lookup keys for existing children: "name|birth_year"
        $existingKeys = $existingChildren->mapWithKeys(function ($child) {
            return [$child->name.'|'.$child->birth_year => $child];
        });

        // Track which existing children are still in snapshot
        $keptChildIds = [];

        foreach ($childrenSnapshot as $snapshotChild) {
            $key = ($snapshotChild['name'] ?? '').'|'.($snapshotChild['birth_year'] ?? '');

            if (isset($existingKeys[$key])) {
                // Update existing child
                $existingKeys[$key]->update([
                    'name' => $snapshotChild['name'],
                    'gender' => $snapshotChild['gender'] ?? null,
                    'birth_month' => $snapshotChild['birth_month'] ?? null,
                    'birth_year' => $snapshotChild['birth_year'] ?? null,
                ]);
                $keptChildIds[] = $existingKeys[$key]->id;
            } else {
                // Insert new child
                $newChild = ClientChild::create([
                    'client_id' => $clientId,
                    'name' => $snapshotChild['name'],
                    'gender' => $snapshotChild['gender'] ?? null,
                    'birth_month' => $snapshotChild['birth_month'] ?? null,
                    'birth_year' => $snapshotChild['birth_year'] ?? null,
                ]);
                $keptChildIds[] = $newChild->id;
            }
        }

        // Delete any profile children not in booking snapshot
        ClientChild::where('client_id', $clientId)
            ->whereNotIn('id', $keptChildIds)
            ->delete();
    }

    /**
     * Sync pets between booking snapshot and client profile.
     * Upsert based on name match.
     * Delete profile pets not in booking snapshot.
     */
    protected function syncClientPets(int $clientId, array $petsSnapshot): void
    {
        $existingPets = ClientPet::where('client_id', $clientId)->get();

        // Build lookup keys for existing pets: "name"
        $existingKeys = $existingPets->mapWithKeys(function ($pet) {
            return [$pet->name => $pet];
        });

        // Track which existing pets are still in snapshot
        $keptPetIds = [];

        foreach ($petsSnapshot as $snapshotPet) {
            $name = $snapshotPet['name'] ?? '';

            if (isset($existingKeys[$name])) {
                // Update existing pet
                $existingKeys[$name]->update([
                    'name' => $snapshotPet['name'],
                    'type' => $snapshotPet['type'] ?? null,
                    'breed' => $snapshotPet['breed'] ?? null,
                    'notes' => $snapshotPet['notes'] ?? null,
                ]);
                $keptPetIds[] = $existingKeys[$name]->id;
            } else {
                // Insert new pet
                $newPet = ClientPet::create([
                    'client_id' => $clientId,
                    'name' => $snapshotPet['name'],
                    'type' => $snapshotPet['type'] ?? null,
                    'breed' => $snapshotPet['breed'] ?? null,
                    'notes' => $snapshotPet['notes'] ?? null,
                ]);
                $keptPetIds[] = $newPet->id;
            }
        }

        // Delete any profile pets not in booking snapshot
        ClientPet::where('client_id', $clientId)
            ->whereNotIn('id', $keptPetIds)
            ->delete();
    }

    /**
     * Save new children to client profile (legacy method - kept for store).
     */
    protected function saveNewChildren(int $clientId, array $children): void
    {
        foreach ($children as $childData) {
            ClientChild::updateOrCreate(
                [
                    'client_id' => $clientId,
                    'name' => $childData['name'] ?? null,
                ],
                [
                    'gender' => $childData['gender'] ?? null,
                    'birth_date' => isset($childData['birth_month']) && isset($childData['birth_year'])
                        ? Carbon::createFromDate((int) $childData['birth_year'], (int) $childData['birth_month'], 1)->format('Y-m-d')
                        : null,
                ]
            );
        }
    }

    /**
     * Save new pets to client profile.
     */
    protected function saveNewPets(int $clientId, array $pets): void
    {
        foreach ($pets as $petData) {
            ClientPet::updateOrCreate(
                [
                    'client_id' => $clientId,
                    'name' => $petData['name'] ?? null,
                ],
                [
                    'type' => $petData['type'] ?? null,
                    'breed' => $petData['breed'] ?? null,
                    'notes' => $petData['notes'] ?? null,
                ]
            );
        }
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
            'booking_id' => 'nullable|exists:bookings,id',
            'client_id' => 'required_without:new_client|exists:clients,id',
            'service_type' => 'nullable|string',
            'start_datetime' => 'nullable|date',
            'end_datetime' => 'nullable|date|after:start_datetime',
        ]);

        $client = Client::with('favoriteCaregivers')->find($validated['client_id']);

        if ($bookingId = $validated['booking_id'] ?? null) {
            $booking = Booking::find($bookingId);
        } else {
            // Create a mock booking if dates are provided
            $booking = null;
            if (! empty($validated['service_type']) && ! empty($validated['start_datetime'])) {
                $booking = new Booking;
                $booking->service_type = $validated['service_type'];
                $booking->start_datetime = $validated['start_datetime'];
                $booking->end_datetime = $validated['end_datetime'];
            }
        }

        $recommended = $this->recommendationService->getRecommendedCaregivers(
            $client,
            $booking,
            20
        );

        return response()->json($recommended->map(function ($item) {
            return [
                'id' => $item['caregiver']->id,
                'name' => $item['caregiver']->first_name.' '.$item['caregiver']->last_name,
                'age' => $item['caregiver']->date_of_birth->age,
                'score' => $item['score'],
                'matchBadge' => $item['matchBadge'],
                'hasBeenNotified' => $item['hasBeenNotified'],
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
