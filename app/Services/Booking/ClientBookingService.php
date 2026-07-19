<?php

namespace App\Services\Booking;

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\DiscoverySource;
use App\Enums\LocationType;
use App\Enums\PetType;
use App\Enums\ServiceType;
use App\Enums\SitterPreference;
use App\Enums\SpecialConsideration;
use App\Events\BookingCreated;
use App\Events\BookingGroupCreated;
use App\Models\AttributeDefinition;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientChild;
use App\Models\ClientPet;
use App\Models\Hotel;
use App\Services\Booking\Contracts\BookingServiceInterface;
use App\Services\ClientPayment\ClientPaymentServiceFactory;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class ClientBookingService implements BookingServiceInterface, HasMiddleware
{
    public function __construct(
        private ClientPaymentServiceFactory $paymentFactory,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('client', except: ['destroy']),
        ];
    }

    /**
     * Show client's available bookings page (Inertia).
     */
    public function index(Request $request)
    {
        $client = $request->user()->client;

        $bookings = $client->bookings()
            ->with(['caregiver.user', 'caregiverRating'])
            ->with(['bookingGroup' => fn ($q) => $q->withCount('bookings')])
            ->orderBy('start_datetime', 'desc')
            ->paginate(10)
            ->through(function ($booking) {
                $group = $booking->bookingGroup;

                return [
                    'id' => $booking->id,
                    'ulid' => $booking->ulid,
                    'service_type' => ServiceType::tryFrom($booking->service_type)?->label() ?? $booking->service_type,
                    'caregiver_name' => $booking->caregiver ? $booking->caregiver->first_name.' '.$booking->caregiver->last_name : null,
                    'start_datetime' => $booking->start_datetime,
                    'end_datetime' => $booking->end_datetime,
                    'status' => $booking->status,
                    'has_review' => $booking->caregiver_rating !== null,
                    'booking_group' => $group ? [
                        'id' => $group->id,
                        'bookings_count' => $group->bookings_count,
                    ] : null,
                ];
            });

        $bookingStatuses = array_map(
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'colors' => $case->colors(),
            ],
            BookingStatus::cases()
        );

        return Inertia::render('client/bookings/index', [
            'bookings' => $bookings,
            'bookingStatuses' => $bookingStatuses,
        ]);
    }

    /**
     * Show the create booking form (Inertia).
     */
    public function create(Request $request)
    {
        $client = $request->user()->client;

        $serviceTypes = array_values(
            array_map(
                fn ($type) => ['value' => $type->value, 'label' => $type->label()],
                array_filter(ServiceType::cases(), fn ($type) => (! str_contains($type->value, 'invoiced')) && (! str_contains($type->value, 'comped')) && (! str_contains($type->value, 'companion')))
            )
        );
        $locationTypes = array_values(
            array_map(
                fn ($type) => ['value' => $type->value, 'label' => $type->label()],
                array_filter(LocationType::cases(), fn ($type) => ! str_contains($type->value, 'event'))
            )
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

        $sitterPreferences = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            SitterPreference::cases(),
        );

        $discoverySources = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            DiscoverySource::cases(),
        );

        return Inertia::render('client/bookings/create', [
            'service_types' => $serviceTypes,
            'location_types' => $locationTypes,
            'pet_types' => array_map(
                fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                PetType::cases()
            ),
            'children' => $client->children ?? collect([]),
            'pets' => $client->pets ?? collect([]),
            'client_addresses' => $client->addresses ?? collect([]),
            'hotels' => $hotels,
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
            'discovery_sources' => $discoverySources,
        ]);
    }

    public function recommendedCaregivers(Request $request): RedirectResponse
    {
        return redirect()->route('dashboard');
    }

    /**
     * Store a new booking (Inertia).
     */
    public function store(Request $request)
    {
        $client = $request->user()->client;

        // Only touch the client's profile (children/pets) when they opted in via
        // the "update my profile" checkbox. When it is unchecked, the profile is
        // left entirely untouched — no creates, updates, or deletes — and the
        // booking simply snapshots the client's current profile below.
        if ($request->save_children_pets_to_profile) {
            if (! empty($request->deleted_child_ids)) {
                ClientChild::where('client_id', $client->id)->whereIn('id', $request->deleted_child_ids)->delete();
            }

            if (! empty($request->deleted_pet_ids)) {
                ClientPet::where('client_id', $client->id)->whereIn('id', $request->deleted_pet_ids)->delete();
            }

            // Upsert children: the form re-submits the client's EXISTING profile
            // children (with their real positive id) alongside any new ones (temp
            // negative id / no id). Update the matched existing rows and only
            // create genuinely new ones — otherwise every booking duplicates the
            // roster.
            if (! empty($request->new_children)) {
                foreach ($request->new_children as $childData) {
                    $birthDate = ! empty($childData['birth_year'])
                        ? Carbon::createFromDate(
                            (int) $childData['birth_year'],
                            (int) ($childData['birth_month'] ?? 0) ?: now()->month,
                            1
                        )->format('Y-m-d')
                        : null;

                    if (! empty($childData['id']) && $childData['id'] > 0) {
                        ClientChild::where('client_id', $client->id)
                            ->where('id', $childData['id'])
                            ->update([
                                'name' => $childData['name'] ?? null,
                                'gender' => $childData['gender'] ?? null,
                                'birth_date' => $birthDate,
                            ]);
                    } else {
                        ClientChild::create([
                            'client_id' => $client->id,
                            'name' => $childData['name'] ?? null,
                            'gender' => $childData['gender'] ?? null,
                            'birth_date' => $birthDate,
                        ]);
                    }
                }
            }

            // Upsert pets — same existing-vs-new handling as children.
            if (! empty($request->new_pets)) {
                foreach ($request->new_pets as $petData) {
                    if (! empty($petData['id']) && $petData['id'] > 0) {
                        ClientPet::where('client_id', $client->id)
                            ->where('id', $petData['id'])
                            ->update([
                                'name' => $petData['name'] ?? null,
                                'type' => $petData['type'] ?? null,
                                'breed' => $petData['breed'] ?? null,
                                'notes' => $petData['notes'] ?? null,
                            ]);
                    } else {
                        ClientPet::create([
                            'client_id' => $client->id,
                            'name' => $petData['name'] ?? null,
                            'type' => $petData['type'] ?? null,
                            'breed' => $petData['breed'] ?? null,
                            'notes' => $petData['notes'] ?? null,
                        ]);
                    }
                }
            }
        }

        // Ensure the client's user is loaded for the email snapshot below.
        $client->loadMissing('user');

        // Snapshot children/pets from the SUBMITTED form list (mirrors
        // AdminBookingService::store), NOT the profile roster — so per-booking
        // edits (a removed or one-off child) are reflected on the booking
        // regardless of whether the client chose to persist them to their profile.
        $childrenSnapshot = collect($request->new_children ?? [])
            ->filter(fn ($child) => ! empty($child['name']))
            ->map(fn ($child) => [
                'name' => $child['name'],
                'gender' => $child['gender'] ?? null,
                'birth_month' => ! empty($child['birth_month']) ? (int) $child['birth_month'] : null,
                'birth_year' => ! empty($child['birth_year']) ? (int) $child['birth_year'] : null,
            ])
            ->values()
            ->all();

        $petsSnapshot = collect($request->new_pets ?? [])
            ->filter(fn ($pet) => ! empty($pet['name']))
            ->map(fn ($pet) => [
                'name' => $pet['name'],
                'type' => $pet['type'] ?? null,
                'breed' => $pet['breed'] ?? null,
                'notes' => $pet['notes'] ?? null,
            ])
            ->values()
            ->all();

        // Create new client address if private_home and new address provided
        $addressId = $request->address_id;
        if ($request->location_type === 'private_home' &&
            empty($addressId) &&
            ! empty($request->address_line1)) {
            $clientAddress = ClientAddress::create([
                'client_id' => $client->id,
                'line1' => $request->address_line1,
                'line2' => $request->address_line2,
                'city' => $request->address_city,
                'state' => $request->address_state,
                'zip' => $request->address_zip,
            ]);
            $addressId = $clientAddress->id;
        }

        // Create booking group with shared fields
        $bookingGroup = BookingGroup::create([
            'client_id' => $client->id,
            'submitted_at' => now(),
            'submission_type' => 'client',
            'service_type' => $request->service_type,
            'location_type' => $request->location_type,
            'address_id' => $addressId,
            'address_line1' => $request->address_line1,
            'address_line2' => $request->address_line2,
            'address_city' => $request->address_city,
            'address_state' => $request->address_state,
            'address_zip' => $request->address_zip,
            'client_first_name' => $client->first_name,
            'client_last_name' => $client->last_name,
            'client_phone' => $client->phone,
            'client_email' => $client->user?->email,
            'children' => $childrenSnapshot,
            'pets' => $petsSnapshot,
            'hotel_id' => $request->hotel_id,
            'hotel_name' => $request->hotel_name,
            'rental_platform' => $request->rental_platform,
            'caregiver_notes' => $request->caregiver_notes,
            'notes_to_sitterwise' => $request->notes_to_sitterwise,
            'sitter_preferences' => $request->sitter_preferences,
            'other_adults_present' => $request->other_adults_present,
            'emergency_instructions' => $request->emergency_instructions,
            'special_needs_notes' => $request->special_needs_notes,
            'how_did_you_hear' => $request->how_did_you_hear,
            'requires_payment' => true,
        ]);

        // Create bookings for each date
        $dates = $request->dates ?? [
            ['start_datetime' => $request->start_datetime, 'end_datetime' => $request->end_datetime],
        ];

        $bookings = [];
        foreach ($dates as $dateEntry) {
            $bookings[] = Booking::create([
                'booking_group_id' => $bookingGroup->id,
                'start_datetime' => $dateEntry['start_datetime'],
                'end_datetime' => $dateEntry['end_datetime'],
                'status' => 'received',
                'payment_status' => 'pending',
                'total_amount' => 0,
            ]);
        }

        $booking = $bookings[0]; // Return first booking for backward compatibility

        // Fire the correct event based on number of dates
        $dates = $request->dates ?? null;
        if ($dates && count($dates) > 1) {
            event(new BookingGroupCreated($bookingGroup));
        } else {
            event(new BookingCreated($booking));
        }

        return redirect('/bookings');
    }

    /**
     * Show a specific booking detail page (Inertia).
     */
    public function show(Request $request, Booking $booking)
    {
        $client = $request->user()->client;

        if ($booking->bookingGroup->client_id !== $client->id) {
            abort(403, 'Unauthorized');
        }

        $booking->load('bookingGroup.bookings.caregiver', 'caregiver.user', 'caregiverRating');

        $paymentService = $this->paymentFactory->make()->setClient($client);

        // Handle Stripe redirect return — store the payment method
        $sessionId = $request->query('session_id');
        if ($sessionId) {
            $setupData = $paymentService->retrieveSetupIntent($sessionId);
            if ($setupData) {
                $paymentService->storePaymentMethod($setupData);
            }
        }

        $requiresPayment = $booking->requires_payment;
        $paymentStatus = $booking->payment_status;
        $hasPaymentMethod = $paymentService->showPaymentMethods() !== [];
        $paymentSetupIntent = null;

        if ($requiresPayment && $paymentStatus === BookingPaymentStatus::Pending->value && ! $hasPaymentMethod) {
            $returnUrl = route('bookings.show', $booking).'?session_id={CHECKOUT_SESSION_ID}';
            $intent = $paymentService->createSetupIntent($returnUrl);
            $paymentSetupIntent = $intent['client_secret'];
        }

        $bookingStatuses = array_map(
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'colors' => $case->colors(),
            ],
            BookingStatus::cases()
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

        return Inertia::render('client/bookings/show', [
            'booking_statuses' => $bookingStatuses,
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
                'caregiver_rating' => $booking->caregiver_rating,
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
                'charge_to_client_hourly' => $booking->charge_to_client_hourly,
                'total_working_hour' => $booking->total_working_hour,
                'charge_to_client' => $booking->charge_to_client,
                // paid_to_caregiver and sitterwise_cut are internal payout/margin
                // figures and must not reach the client; only what the client
                // pays (charge_to_client, tip, reimbursement) is exposed.
                'tip' => $booking->tip,
                'reimbursement' => $booking->reimbursement,
                'reimbursement_description' => $booking->reimbursement_description,
                'children' => $booking->children,
                'children_notes' => $booking->children_notes,
                'pets' => $booking->pets,
                'booking_group' => $group ? [
                    'id' => $group->id,
                    'bookings_count' => $group->bookings->count(),
                    'sibling_bookings' => $siblingBookings,
                ] : null,
                'requires_payment' => $requiresPayment,
                'payment_status' => $paymentStatus,
                'payment_setup_intent' => $paymentSetupIntent,
                'has_payment_method' => $hasPaymentMethod,
            ],
        ]);
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
        abort(403, 'Caregivers cannot delete bookings');
    }

    /**
     * Confirm a reserved booking (atomic operation).
     */
    public function confirm(Request $request, Booking $booking)
    {
        abort(403, 'Caregivers cannot delete bookings');
    }

    /**
     * Release a reservation.
     */
    public function release(Request $request, Booking $booking)
    {
        abort(403, 'Caregivers cannot delete bookings');
    }

    public function processPayment(Request $request, Booking $booking)
    {
        abort(403, 'Clients cannot process payments');
    }
}
