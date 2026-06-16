<?php

namespace App\Services\Booking;

use App\Enums\AssignmentResolution;
use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\CaregiverStatus;
use App\Enums\ClientType;
use App\Enums\DiscoverySource;
use App\Enums\LocationType;
use App\Enums\PetType;
use App\Enums\ServiceType;
use App\Enums\SitterPreference;
use App\Enums\SpecialConsideration;
use App\Events\BookingAccepted;
use App\Events\BookingCancelled;
use App\Events\BookingCreated;
use App\Events\BookingGroupCreated;
use App\Events\BookingGroupSplit;
use App\Jobs\NotifyCaregiversJob;
use App\Models\AttributeDefinition;
use App\Models\Booking;
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
use App\Services\CaregiverRecommendation\AvailabilityReservationService;
use App\Services\CaregiverRecommendation\CaregiverRecommendationService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

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
        $endDate = $startDate->endOfMonth()->addDay();

        $query = Booking::select([
            'id', 'ulid', 'booking_group_id', 'caregiver_id',
            'start_datetime', 'end_datetime', 'status', 'payment_status',
        ])->with([
            'bookingGroup:id,service_type,location_type,client_id,client_first_name,client_last_name,hotel_id,address_line1,address_line2,address_city,address_state,address_zip,children,pets,children_notes',
            'caregiver:id,first_name,last_name',
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

        $groupIds = $bookings->pluck('booking_group_id')->unique()->filter();
        $counts = Booking::whereIn('booking_group_id', $groupIds)
            ->selectRaw('booking_group_id, count(*) as count')
            ->groupBy('booking_group_id')
            ->pluck('count', 'booking_group_id');

        $bookings->each(function (Booking $booking) use ($counts) {
            $booking->setAppends([]);
            $booking->bookingGroup?->setAttribute('bookings_count', (int) $counts->get($booking->booking_group_id, 1));
            $booking->bookingGroup?->setHidden(['created_at', 'updated_at', 'deleted_at']);
        });

        $serviceTypes = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            ServiceType::cases()
        );

        $locationTypes = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            LocationType::cases()
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

        $sitterPreferences = array_map(
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

        $caregivers = Caregiver::query()
            ->where('status', CaregiverStatus::Active->value)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->first_name.' '.$c->last_name,
            ]);

        return Inertia::render('admin/bookings/index', [
            'bookings' => $bookings,
            'service_types' => $serviceTypes,
            'location_types' => $locationTypes,
            'pet_types' => array_map(
                fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                PetType::cases()
            ),
            'booking_statuses' => $bookingStatuses,
            'payment_statuses' => $paymentStatuses,
            'sitter_preferences' => $sitterPreferences,
            'booking_attributes' => AttributeDefinition::where('entity_type', 'booking')->get(),
            'client_types' => array_map(
                fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                ClientType::cases()
            ),
            'discovery_sources' => array_map(
                fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                DiscoverySource::cases()
            ),
            'filters' => [
                'month' => (int) $month,
                'year' => (int) $year,
                'status' => $status,
            ],
            'hotels' => $hotels,
            'clients' => Inertia::defer(fn () => Client::query()->get()->map(fn ($c) => [
                'id' => $c->id,
                'name' => trim($c->first_name.' '.$c->last_name),
            ])),
            'caregivers' => $caregivers,

        ]);
    }

    public function create(Request $request): RedirectResponse
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
            try {
                $user = User::create([
                    'name' => $validated['new_client']['first_name'].' '.$validated['new_client']['last_name'],
                    'email' => $validated['new_client']['email'],
                    'password' => \Hash::make(\Str::random(16)),
                    'role' => 'client',
                ]);
            } catch (QueryException $e) {
                if ($e->getCode() === '23000') {
                    throw ValidationException::withMessages([
                        'new_client.email' => 'This email is already registered to another account.',
                    ]);
                }

                throw $e;
            }

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

        $isGroupBooking = $validated['service_type'] === 'group_childcare_invoiced';
        $hasChildrenNotes = ! empty($validated['children_notes']);

        // Prepare children snapshot (filter profile by child_ids + add new_children)
        if ($isGroupBooking && $hasChildrenNotes) {
            $childrenSnapshot = null;
        } else {
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
        if (! $isGroupBooking && ! empty($validated['new_children']) && ($validated['save_children_pets_to_profile'] ?? true) && $clientId) {
            $this->saveNewChildren($clientId, $validated['new_children']);
        }

        if (! empty($validated['new_pets']) && ($validated['save_children_pets_to_profile'] ?? true) && $clientId) {
            $this->saveNewPets($clientId, $validated['new_pets']);
        }

        if (($validated['save_children_pets_to_profile'] ?? true) && $clientId) {
            $this->saveClientAddress($clientId, $validated);
        }

        $bookingGroup = BookingGroup::create([
            'client_id' => $clientId,
            'submitted_at' => now(),
            'submission_type' => 'admin',
            'service_type' => $validated['service_type'],
            'location_type' => $validated['location_type'],
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
            'children_notes' => $isGroupBooking ? ($validated['children_notes'] ?? null) : null,
            'pets' => $petsSnapshot,
            'hotel_id' => $validated['hotel_id'] ?? null,
            'hotel_name' => $validated['hotel_name'] ?? null,
            'rental_platform' => $validated['rental_platform'] ?? null,
            'caregiver_notes' => $validated['caregiver_notes'] ?? null,
            'notes_to_sitterwise' => $validated['notes_to_sitterwise'] ?? null,
            'admin_notes' => $validated['admin_notes'] ?? null,
            'corporate_id' => $validated['corporate_id'] ?? null,
            'sitter_preferences' => $validated['sitter_preferences'] ?? null,
            'other_adults_present' => $validated['other_adults_present'] ?? null,
            'special_needs_notes' => $validated['special_needs_notes'] ?? null,
            'emergency_instructions' => $validated['emergency_instructions'] ?? null,
            'requires_payment' => $validated['requires_payment'] ?? true,
        ]);

        // Create bookings for each date
        $dates = $validated['dates'] ?? [
            ['start_datetime' => $validated['start_datetime'], 'end_datetime' => $validated['end_datetime']],
        ];

        $bookings = [];
        foreach ($dates as $dateEntry) {
            $bookings[] = Booking::create([
                'booking_group_id' => $bookingGroup->id,
                'caregiver_id' => $validated['caregiver_id'] ?? null,
                'availability_id' => null,
                'start_datetime' => $dateEntry['start_datetime'],
                'end_datetime' => $dateEntry['end_datetime'],
                'status' => $validated['status'],
                'total_amount' => 0,
                'payment_status' => $validated['payment_status'],
            ]);
        }

        $booking = $bookings[0]; // First booking for event dispatch

        // Fire the correct event based on number of dates
        $dates = $validated['dates'] ?? null;
        if ($dates && count($dates) > 1) {
            event(new BookingGroupCreated($bookingGroup));
        } else {
            event(new BookingCreated($booking));
        }

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
                'bookingGroup.bookings.caregiver',
            ]);

            $booking->client->setRelation(
                'previousCaregivers',
                $booking->client->previousCaregivers()->with('user')->get()
            );

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
                    'caregiver_name' => $b->caregiver
                        ? $b->caregiver->first_name.' '.$b->caregiver->last_name
                        : null,
                ]);

            $data = $booking->toArray();
            $data['booking_group'] = $group ? [
                'id' => $group->id,
                'client_id' => $group->client_id,
                'address_city' => $group->address_city,
                'children' => $group->children,
                'pets' => $group->pets,
                'children_notes' => $group->children_notes,
                'service_type' => $group->service_type,
                'location_type' => $group->location_type,
                'bookings_count' => $group->bookings->count(),
                'sibling_bookings' => $siblingBookings,
            ] : null;

            return response()->json($data);
        }

        $bookingStatuses = array_map(
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'colors' => $case->colors(),
            ],
            BookingStatus::cases()
        );

        $booking->load([
            'caregiver.user',
            'clientRating',
            'caregiverRating',
            'bookingGroup.bookings.caregiver',
            'assignments',
            'client.favoriteCaregivers',
            'client.blockedCaregivers',
        ]);

        $assignmentResolution = null;
        if ($booking->caregiver_id) {
            $currentAssignment = $booking->assignments
                ->where('caregiver_id', $booking->caregiver_id)
                ->first();
            $assignmentResolution = $currentAssignment?->resolution;
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
                'caregiver_name' => $b->caregiver
                    ? $b->caregiver->first_name.' '.$b->caregiver->last_name
                    : null,
            ]);

        $recommended = $this->recommendationService->getRecommendedCaregivers($booking->client, $booking);
        $caregiverSuggestions = $recommended->values()->toArray();
        $caregiverAllIds = $recommended->pluck('id')->toArray();
        $caregiverTotal = $recommended->count();

        return Inertia::render('admin/bookings/show', [
            'booking_statuses' => $bookingStatuses,
            'caregiver_suggestions' => $caregiverSuggestions,
            'caregiver_all_ids' => $caregiverAllIds,
            'caregiver_total' => $caregiverTotal,
            'booking' => [
                'id' => $booking->id,
                'ulid' => $booking->ulid,
                'service_type' => ServiceType::tryFrom($booking->service_type)?->label() ?? $booking->service_type,
                'client_id' => $booking->client_id,
                'client_name' => $booking->client->first_name.' '.$booking->client->last_name,
                'client_phone' => $booking->client_phone ?? $booking->client->user?->phone,
                'client_email' => $booking->client_email ?? $booking->client->user?->email,
                'caregiver_id' => $booking->caregiver_id,
                'caregiver_name' => $booking->caregiver
                    ? $booking->caregiver->first_name.' '.$booking->caregiver->last_name
                    : null,
                'assignment_resolution' => $assignmentResolution,
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
                'hotel_name' => $booking->bookingGroup->hotel_name ?? $booking->hotel?->name,
                'location_type' => $booking->location_type,
                'charge_to_client' => $booking->charge_to_client,
                'paid_to_caregiver' => $booking->paid_to_caregiver,
                'sitterwise_cut' => $booking->sitterwise_cut,
                'tip' => $booking->tip,
                'reimbursement' => $booking->reimbursement,
                'children' => $booking->children,
                'children_notes' => $booking->children_notes,
                'pets' => $booking->pets,
                'client_rating' => $booking->client_rating,
                'caregiver_rating' => $booking->caregiver_rating,
                'booking_group' => $group ? [
                    'id' => $group->id,
                    'bookings_count' => $group->bookings->count(),
                    'sibling_bookings' => $siblingBookings,
                ] : null,
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

        $isGroupBooking = ($validated['service_type'] ?? $booking->service_type) === 'group_childcare_invoiced';
        $hasChildrenNotes = ! empty($validated['children_notes']);

        // Build children snapshot directly from new_children input
        $childrenSnapshot = null;
        if ($isGroupBooking && $hasChildrenNotes) {
            $childrenSnapshot = null;
        } elseif ($request->has('new_children')) {
            $childrenSnapshot = [];
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
        if (! $isGroupBooking && ($validated['save_children_pets_to_profile'] ?? false) && $booking->client_id) {
            // Use childrenSnapshot (which may be existing or new)
            $this->syncClientChildren($booking->client_id, $childrenSnapshot);
            $this->syncClientPets($booking->client_id, $petsSnapshot);
            $this->saveClientAddress($booking->client_id, $validated);
        }

        $groupOnlyFields = [
            'client_id', 'service_type', 'location_type', 'rental_platform',
            'client_first_name', 'client_last_name', 'client_phone', 'client_email',
            'address_line1', 'address_line2', 'address_city', 'address_state', 'address_zip',
            'hotel_name', 'children', 'pets',
            'sitter_preferences', 'other_adults_present', 'special_needs_notes',
            'emergency_instructions', 'how_did_you_hear',
            'caregiver_notes', 'notes_to_sitterwise', 'admin_notes', 'corporate_id',
            'special_considerations',
        ];

        $nonColumnFields = [
            'new_children', 'new_pets',
            'deleted_child_ids', 'deleted_pet_ids',
            'child_ids', 'pet_ids',
            'save_children_pets_to_profile',
        ];

        $updateData = [
            ...collect($validated)->except([
                ...$groupOnlyFields,
                ...$nonColumnFields,
            ])->toArray(),
            'hotel_id' => $validated['hotel_id'] ?? $booking->hotel_id,
            'address_id' => $addressId,
            'children_notes' => $isGroupBooking ? ($validated['children_notes'] ?? null) : null,
        ];

        $groupUpdateData = collect($validated)->only([
            ...$groupOnlyFields,
            'hotel_id', 'requires_payment',
        ])->toArray();
        $groupUpdateData['client_first_name'] = $client?->first_name;
        $groupUpdateData['client_last_name'] = $client?->last_name;
        $groupUpdateData['client_phone'] = $client?->phone;
        $groupUpdateData['client_email'] = $client?->user?->email;
        $groupUpdateData['children'] = $childrenSnapshot;
        $groupUpdateData['pets'] = $petsSnapshot;
        $groupUpdateData['children_notes'] = $isGroupBooking ? ($validated['children_notes'] ?? null) : null;

        $oldCaregiverId = $booking->caregiver_id;

        if (! empty($groupUpdateData)) {
            $booking->bookingGroup->update($groupUpdateData);
        }

        $booking->update($updateData);

        if ($oldCaregiverId && ! $booking->caregiver_id) {
            $nonRevertableStatuses = [
                BookingStatus::Completed->value,
                BookingStatus::Paid->value,
                BookingStatus::Cancelled->value,
            ];

            if (! in_array($booking->status, $nonRevertableStatuses, true)) {
                $booking->updateQuietly(['status' => BookingStatus::Received->value]);

                $assignment = $booking->assignments()->unresolved()->first();
                if ($assignment) {
                    $assignment->resolve(
                        AssignmentResolution::Reassigned,
                        'Caregiver unassigned via booking edit',
                    );
                }
            }
        }

        if ($booking->caregiver_id && ! $oldCaregiverId && $booking->getOriginal('status') === BookingStatus::Received->value) {
            $booking->updateQuietly(['status' => BookingStatus::Confirmed->value]);
        }

        $booking->load('bookingGroup');

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

    /**
     * Save address to client profile if it doesn't already exist.
     * Creates a new ClientAddress record only when no matching address exists.
     */
    protected function saveClientAddress(int $clientId, array $validated): void
    {
        if (empty($validated['address_line1'])) {
            return;
        }

        $exists = ClientAddress::where('client_id', $clientId)
            ->where('line1', $validated['address_line1'])
            ->where('line2', $validated['address_line2'] ?? null)
            ->where('city', $validated['address_city'] ?? '')
            ->where('state', $validated['address_state'] ?? '')
            ->where('zip', $validated['address_zip'] ?? '')
            ->exists();

        if (! $exists) {
            $locationType = $validated['location_type'] ?? null;

            $label = match ($locationType) {
                'hotel' => Hotel::where('id', $validated['hotel_id'])->value('name'),
                default => $locationType,
            };

            ClientAddress::create([
                'client_id' => $clientId,
                'label' => $label,
                'line1' => $validated['address_line1'],
                'line2' => $validated['address_line2'] ?? null,
                'city' => $validated['address_city'] ?? '',
                'state' => $validated['address_state'] ?? '',
                'zip' => $validated['address_zip'] ?? '',
                'location_type' => $locationType,
            ]);
        }
    }

    public function cancel(Request $request, Booking $booking)
    {
        if ($booking->status === BookingStatus::Cancelled->value) {
            return redirect()->back()->with('error', 'Booking is already cancelled.');
        }

        DB::transaction(function () use ($booking, $request) {
            $booking->update([
                'status' => BookingStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancellation_reason' => $request->input('reason'),
                'cancelled_by' => auth()->id(),
                'charge_to_client' => 0,
                'paid_to_caregiver' => 0,
                'sitterwise_cut' => 0,
                'total_service_amount' => 0,
                'total_amount' => 0,
            ]);

            $assignment = $booking->assignments()->unresolved()->first();

            if ($assignment) {
                $assignment->resolve(AssignmentResolution::CancelledBySitterwise, $request->input('reason'));
            }
        });

        event(new BookingCancelled($booking->fresh(), $request->input('reason'), $request->user()));

        return redirect()->back()->with('success', 'Booking cancelled successfully.');
    }

    public function replaceCaregiver(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'caregiver_id' => 'required|exists:caregivers,id',
        ]);

        $finalized = [BookingStatus::Completed->value, BookingStatus::Paid->value, BookingStatus::Cancelled->value];

        if (in_array($booking->status, $finalized, true)) {
            return redirect()->back()->with('error', 'Cannot replace caregiver on a finalized booking.');
        }

        $newCaregiver = Caregiver::where('id', $validated['caregiver_id'])
            ->where('status', CaregiverStatus::Active->value)
            ->first();

        if (! $newCaregiver) {
            return redirect()->back()->with('error', 'Selected caregiver is not active.');
        }

        DB::transaction(function () use ($booking, $validated) {
            $currentAssignment = $booking->assignments()
                ->unresolved()
                ->first();

            if ($currentAssignment) {
                $currentAssignment->resolve(
                    AssignmentResolution::Reassigned,
                    'Replaced via Replace Caregiver flow',
                );
            }

            $booking->update(['caregiver_id' => $validated['caregiver_id']]);

            $booking->assignments()->create([
                'caregiver_id' => $validated['caregiver_id'],
                'assigned_at' => now(),
            ]);
        });

        return redirect()->back()->with('success', 'Caregiver replaced successfully.');
    }

    public function destroy(Booking $booking)
    {
        $booking->forceDelete();

        return redirect()->back()->with('success', 'Booking deleted permanently.');
    }

    public function notify(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'caregiver_ids' => 'required|array',
            'caregiver_ids.*' => 'exists:caregivers,id',
        ]);

        $notifiableStatuses = [BookingStatus::Received->value, BookingStatus::Pending->value];
        if (! in_array($booking->status, $notifiableStatuses, true)) {
            return redirect()->back()->with('error', 'Cannot notify caregivers for a booking with status '.$booking->status.'.');
        }

        NotifyCaregiversJob::dispatch($booking, $validated['caregiver_ids']);

        if ($booking->status === BookingStatus::Received->value) {
            $booking->updateQuietly(['status' => BookingStatus::Pending->value]);
        }

        return redirect()->back()->with('success', 'Notifications queued.');
    }

    public function recommendedCaregivers(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'nullable|exists:bookings,id',
            'client_id' => 'required_without:new_client|exists:clients,id',
            'service_type' => 'nullable|string',
            'start_datetime' => 'nullable|date',
            'end_datetime' => 'nullable|date|after:start_datetime',
            'address_city' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'age_filter' => 'nullable|in:all,younger,seasoned',
        ]);

        $client = Client::with('favoriteCaregivers')->find($validated['client_id']);

        $dateRanges = [];

        if ($bookingId = $validated['booking_id'] ?? null) {
            $booking = Booking::with('bookingGroup.bookings')->find($bookingId);

            if ($booking && $booking->bookingGroup && $booking->bookingGroup->bookings->isNotEmpty()) {
                $dateRanges = $booking->bookingGroup->bookings
                    ->map(fn (Booking $b) => ['start' => $b->start_datetime, 'end' => $b->end_datetime])
                    ->values()
                    ->toArray();
            }
        } else {
            // Create a mock booking if dates are provided
            $booking = null;
            if (! empty($validated['service_type']) && ! empty($validated['start_datetime'])) {
                $booking = new Booking;
                $booking->service_type = $validated['service_type'];
                $booking->start_datetime = $validated['start_datetime'];
                $booking->end_datetime = $validated['end_datetime'];
                $booking->address_city = $validated['address_city'] ?? null;
            }
        }

        $cacheKey = 'recommended_cg:'.md5(json_encode([
            'client_id' => $validated['client_id'],
            'booking_id' => $validated['booking_id'] ?? null,
            'service_type' => $validated['service_type'] ?? null,
            'start_datetime' => $validated['start_datetime'] ?? null,
            'end_datetime' => $validated['end_datetime'] ?? null,
            'address_city' => $validated['address_city'] ?? null,
        ]));

        $page = (int) ($validated['page'] ?? 1);

        if ($page === 1) {
            $recommended = $this->recommendationService->getRecommendedCaregivers(
                $client,
                $booking,
                dateRanges: $dateRanges,
            );

            Cache::put($cacheKey, $recommended->toArray(), 300);
        } else {
            $cached = Cache::get($cacheKey);

            $recommended = $cached !== null
                ? collect($cached)
                : $this->recommendationService->getRecommendedCaregivers(
                    $client,
                    $booking,
                    dateRanges: $dateRanges,
                );
        }

        if (($validated['age_filter'] ?? 'all') === 'younger') {
            $recommended = $recommended->filter(fn ($cg) => $cg['age'] === null || $cg['age'] < 35);
        } elseif (($validated['age_filter'] ?? 'all') === 'seasoned') {
            $recommended = $recommended->filter(fn ($cg) => $cg['age'] === null || $cg['age'] >= 35);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $total = $recommended->count();
        $paginated = $recommended->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $paginated,
            'all_ids' => $recommended->pluck('id'),
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
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

    public function splitGroup(Request $request, BookingGroup $group): RedirectResponse
    {
        $group->loadMissing('bookings');

        $validated = $request->validate([
            'booking_ids' => ['required', 'array', 'min:1'],
            'booking_ids.*' => ['required', 'integer', 'exists:bookings,id'],
        ]);

        $extractedIds = $validated['booking_ids'];

        $groupBookingIds = $group->bookings->pluck('id')->toArray();
        $invalidIds = array_diff($extractedIds, $groupBookingIds);

        if (! empty($invalidIds)) {
            logger()->debug('splitGroup guard: invalid IDs');

            return back()->with('error', 'Some booking IDs do not belong to this group.');
        }

        if (count($extractedIds) >= $group->bookings->count()) {
            logger()->debug('splitGroup guard: would empty group');

            return back()->with('error', 'Cannot move all bookings — group would be empty.');
        }

        $firstBooking = null;

        DB::transaction(function () use ($group, $extractedIds, &$firstBooking) {
            $extractedBookings = Booking::whereIn('id', $extractedIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $reservationService = app(AvailabilityReservationService::class);

            foreach ($extractedBookings as $extractedBooking) {
                if ($extractedBooking->caregiver_id) {
                    $reservationService->release($extractedBooking);
                }
            }

            $newGroup = $group->replicate();
            $newGroup->submitted_at = now();
            $newGroup->submission_type = 'admin';
            $newGroup->save();

            Booking::whereIn('id', $extractedIds)->update([
                'booking_group_id' => $newGroup->id,
                'status' => 'received',
                'caregiver_id' => null,
                'reserved_by' => null,
                'reservation_expires_at' => null,
                'confirmed_by' => null,
                'confirmed_at' => null,
            ]);

            $firstBooking = Booking::find($extractedIds[0]);
        });

        event(new BookingGroupSplit($group, BookingGroup::find($firstBooking->booking_group_id), $extractedIds));

        return redirect()->to('/bookings/'.$firstBooking->ulid)
            ->with('success', 'Group split successfully.');
    }

    public function export(Request $request)
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $startDate = now()->year($year)->month($month)->startOfMonth();
        $endDate = $startDate->endOfMonth();

        $bookings = Booking::with([
            'client.user',
            'client.children',
            'client.pets',
            'hotel',
            'address',
            'caregiver.user',
            'caregiverNotifications',
        ])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_datetime', [$startDate, $endDate])
                    ->orWhereBetween('end_datetime', [$startDate, $endDate]);
            })
            ->orderBy('start_datetime', 'asc')
            ->get();

        $monthName = now()->month($month)->format('F');

        $hotels = Hotel::where('is_active', true)->get()->keyBy('id');

        $filename = "bookings-{$monthName}-{$year}.xlsx";
        $tempPath = tempnam(sys_get_temp_dir(), 'export_');

        $writer = new Writer;
        $writer->openToFile($tempPath);

        $writer->addRow(Row::fromValues([
            'Booking ID',
            'ULID',
            'Client Name',
            'Client Email',
            'Client Phone',
            'Service Type',
            'Location Type',
            'Hotel',
            'Address',
            'Start Date',
            'Start Time',
            'End Date',
            'End Time',
            'Total Hours',
            'Caregiver Name',
            'Status',
            'Payment Status',
            'Charge to Client',
            'Paid to Caregiver',
            'Sitterwise Cut',
            'Reimbursement',
            'Tip',
            'Bonus',
            'Total Amount',
            'Created At',
        ]));

        foreach ($bookings as $booking) {
            $hotel = $booking->hotel_id ? ($hotels[$booking->hotel_id] ?? null) : null;

            $writer->addRow(Row::fromValues([
                $booking->id,
                $booking->ulid,
                $booking->client?->first_name.' '.$booking->client?->last_name,
                $booking->client?->user?->email,
                $booking->client?->phone,
                ServiceType::tryFrom($booking->service_type)?->label() ?? $booking->service_type,
                LocationType::tryFrom($booking->location_type)?->label() ?? $booking->location_type,
                $hotel?->name,
                collect([
                    $booking->address_line1,
                    $booking->address_line2,
                    $booking->address_city,
                    $booking->address_state,
                    $booking->address_zip,
                ])->filter()->implode(', '),
                $booking->start_datetime?->copy()->setTimezone('America/Los_Angeles')->format('Y-m-d'),
                $booking->start_datetime?->copy()->setTimezone('America/Los_Angeles')->format('H:i'),
                $booking->end_datetime?->copy()->setTimezone('America/Los_Angeles')->format('Y-m-d'),
                $booking->end_datetime?->copy()->setTimezone('America/Los_Angeles')->format('H:i'),
                $booking->total_working_hour,
                $booking->caregiver?->first_name.' '.$booking->caregiver?->last_name,
                $booking->status,
                $booking->payment_status,
                $booking->charge_to_client,
                $booking->paid_to_caregiver,
                $booking->sitterwise_cut,
                $booking->reimbursement,
                $booking->tip,
                $booking->bonus,
                $booking->total_amount,
                $booking->created_at?->format('Y-m-d H:i'),
            ]));
        }

        $writer->close();

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }

    public function processPayment(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'checkout_at' => 'nullable|date',
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
                'checkout_at' => $validated['checkout_at'] ?? $booking->checkout_at,
                'total_working_hour' => $validated['total_working_hour'] ?? $booking->total_working_hour ?? 0,
                'reimbursement' => $validated['reimbursement'] ?? 0,
                'reimbursement_description' => $validated['reimbursement_description'] ?? null,
                'tip' => $validated['tip'] ?? 0,
                'bonus' => $validated['bonus'] ?? 0,
            ],
        ]);

        $success = $booking->update([
            'checkout_at' => $validated['checkout_at'] ?? $booking->checkout_at,
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
