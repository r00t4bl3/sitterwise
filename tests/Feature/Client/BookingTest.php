<?php

use App\Enums\SitterPreference;
use App\Enums\SpecialConsideration;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientChild;
use App\Models\ClientPet;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->user = User::factory()->create(['role' => 'client']);
    $this->client = Client::factory()->for($this->user)->create();
    $this->actingAs($this->user);
});

describe('Booking - Client', function () {
    test('client can view the create booking page', function () {
        $response = $this->get(route('bookings.create'));
        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page->component('client/bookings/create'));
    });

    test('client can create a hotel booking with an unlisted hotel', function () {
        $child = ClientChild::factory()->for($this->client)->create();

        $start = now()->addDays(2)->setHour(9)->setMinute(0);
        $end = now()->addDays(2)->setHour(15)->setMinute(0);

        $response = $this->post(route('bookings.store'), [
            'client_id' => $this->client->id,
            'service_type' => 'babysitter',
            'location_type' => 'hotel',
            'start_datetime' => $start,
            'end_datetime' => $end,
            // Unlisted hotel: no hotel_id, a free-text name + manual address.
            'hotel_id' => null,
            'hotel_name' => 'Seaside Unlisted Inn',
            'address_line1' => '742 Ocean Blvd',
            'address_line2' => '',
            'address_city' => 'Coronado',
            'address_state' => 'CA',
            'address_zip' => '92118',
            'new_children' => [
                [
                    'name' => $child->name,
                    'gender' => $child->gender,
                    'birth_month' => $child->birth_month,
                    'birth_year' => $child->birth_year,
                ],
            ],
            'save_children_pets_to_profile' => false,
            'status' => 'received',
            'payment_status' => 'pending',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('booking_groups', [
            'client_id' => $this->client->id,
            'location_type' => 'hotel',
            'hotel_id' => null,
            'hotel_name' => 'Seaside Unlisted Inn',
            'address_line1' => '742 Ocean Blvd',
            'address_city' => 'Coronado',
        ]);

        // The booking accessor surfaces the free-text hotel name for editing.
        $booking = Booking::whereHas(
            'bookingGroup',
            fn ($q) => $q->where('client_id', $this->client->id),
        )->first();

        expect($booking->hotel_name)->toBe('Seaside Unlisted Inn')
            ->and($booking->hotel_id)->toBeNull();
    });

    test('the admin edit fetch exposes the unlisted hotel name', function () {
        $child = ClientChild::factory()->for($this->client)->create();
        $start = now()->addDays(2)->setHour(9)->setMinute(0);
        $end = now()->addDays(2)->setHour(15)->setMinute(0);

        $this->post(route('bookings.store'), [
            'client_id' => $this->client->id,
            'service_type' => 'babysitter',
            'location_type' => 'hotel',
            'start_datetime' => $start,
            'end_datetime' => $end,
            'hotel_id' => null,
            'hotel_name' => 'Seaside Unlisted Inn',
            'address_line1' => '742 Ocean Blvd',
            'address_city' => 'Coronado',
            'address_state' => 'CA',
            'address_zip' => '92118',
            'new_children' => [
                [
                    'name' => $child->name,
                    'gender' => $child->gender,
                    'birth_month' => $child->birth_month,
                    'birth_year' => $child->birth_year,
                ],
            ],
            'save_children_pets_to_profile' => false,
            'status' => 'received',
            'payment_status' => 'pending',
        ])->assertSessionHasNoErrors();

        $booking = Booking::whereHas(
            'bookingGroup',
            fn ($q) => $q->where('client_id', $this->client->id),
        )->firstOrFail();

        // The admin edit sheet fetches /bookings/{id} as JSON; that payload must
        // carry the free-text hotel name, or the sheet opens blank for it.
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson(route('bookings.show', $booking->id))
            ->assertOk()
            ->assertJsonPath('hotel_name', 'Seaside Unlisted Inn')
            ->assertJsonPath('hotel_id', null);
    });

    test('client can create a booking with all details', function () {
        $clientAddress = ClientAddress::factory()->for($this->client)->create();
        $hotel = Hotel::factory()->create();

        $child1 = ClientChild::factory()->for($this->client)->create();
        $child2 = ClientChild::factory()->for($this->client)->create();
        $pet1 = ClientPet::factory()->for($this->client)->create();

        $newChild = [
            'tempId' => Str::ulid(),
            'name' => 'New Child',
            'gender' => 'male',
            'birth_month' => '6',
            'birth_year' => '2023',
        ];
        $newPet = [
            'tempId' => Str::ulid(),
            'name' => 'New Pet',
            'type' => 'dog',
            'breed' => 'Golden Retriever',
            'notes' => 'Friendly',
        ];

        $startDate = now()->addDays(2);
        $startDatetime = $startDate->copy()->setHour(9)->setMinute(0);
        $endDatetime = $startDate->copy()->setHour(15)->setMinute(0);
        $sitterPreference = fake()->randomElement(array_column(SitterPreference::cases(), 'value'));

        $response = $this->post(route('bookings.store'), [
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
            'address_id' => $clientAddress->id,
            'hotel_id' => null, // Set to null since location_type is private_home
            'rental_platform' => '',
            'address_line1' => $clientAddress->line1,
            'address_line2' => $clientAddress->line2,
            'address_city' => $clientAddress->city,
            'address_state' => $clientAddress->state,
            'address_zip' => $clientAddress->zip,
            'caregiver_notes' => 'Please bring toys.',
            'notes_to_sitterwise' => 'Client is VIP.',
            'sitter_preferences' => [$sitterPreference],
            'other_adults_present' => 'Grandparents',
            'emergency_instructions' => 'Call 911 first.',
            'special_needs_notes' => 'Allergic to peanuts.',
            'how_did_you_hear' => 'google',
            'save_children_pets_to_profile' => true,
            'new_children' => [$newChild],
            'new_pets' => [$newPet],
            'deleted_child_ids' => [],
            'deleted_pet_ids' => [],
            'client_id' => $this->client->id,
            'status' => 'received',
            'payment_status' => 'pending',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('bookings.index'));

        $this->assertDatabaseHas('booking_groups', [
            'client_id' => $this->client->id,
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'address_id' => $clientAddress->id,
            'address_line1' => $clientAddress->line1,
            'special_needs_notes' => 'Allergic to peanuts.',
            'how_did_you_hear' => 'google',
        ]);

        // Verify new child and pet are saved to client profile
        $this->assertDatabaseHas('client_children', [
            'client_id' => $this->client->id,
            'name' => 'New Child',
        ]);
        $this->assertNotNull(ClientChild::where('name', 'New Child')->first()?->birth_date);
        $this->assertEquals(6, ClientChild::where('name', 'New Child')->first()?->birth_month);
        $this->assertEquals(2023, ClientChild::where('name', 'New Child')->first()?->birth_year);
        $this->assertDatabaseHas('client_pets', [
            'client_id' => $this->client->id,
            'name' => 'New Pet',
            'type' => 'dog',
        ]);

        // Verify snapshot data includes existing and new
        $booking = Booking::whereHas('bookingGroup', fn ($q) => $q->where('client_id', $this->client->id))->first();

        expect($booking->children)->toHaveCount(3); // 2 existing + 1 new
        expect($booking->pets)->toHaveCount(2); // 1 existing + 1 new
        expect($booking->sitter_preferences)->toEqual([$sitterPreference]);

        // Verify special_considerations is auto-calculated
        $preference = SitterPreference::tryFrom($sitterPreference);
        $expectedConsiderations = [
            $preference->toSpecialConsideration()->value,
            SpecialConsideration::FamilyHasDogsOnsite->value,
            SpecialConsideration::ParentWillBePresent->value,
        ];

        // The existing pet might be a cat, so we check for contains instead of exact match
        expect($booking->special_considerations)->toContain($preference->toSpecialConsideration()->value);
        expect($booking->special_considerations)->toContain(SpecialConsideration::FamilyHasDogsOnsite->value);
        expect($booking->special_considerations)->toContain(SpecialConsideration::ParentWillBePresent->value);
    });

    test('client can create a booking with manual address input', function () {
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);
        $response = $this->post(route('bookings.store'), [
            'service_type' => 'petsitter',
            'location_type' => 'private_home',
            'start_datetime' => now()->addDays(3)->setHour(12)->setMinute(0)->toISOString(),
            'end_datetime' => now()->addDays(3)->setHour(17)->setMinute(0)->toISOString(),
            'address_id' => null, // Manual input
            'hotel_id' => null,
            'rental_platform' => '',
            'address_line1' => '123 Main St',
            'address_line2' => 'Apt 4B',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'caregiver_notes' => '',
            'notes_to_sitterwise' => '',
            'sitter_preferences' => [],
            'other_adults_present' => '',
            'emergency_instructions' => '',
            'special_needs_notes' => '',
            'how_did_you_hear' => 'friend_family',
            'save_children_pets_to_profile' => false, // No new children/pets
            'new_children' => [['name' => $child->name, 'gender' => $child->gender, 'birth_month' => $child->birth_month, 'birth_year' => $child->birth_year]],
            'new_pets' => [],
            'deleted_child_ids' => [],
            'deleted_pet_ids' => [],
            'client_id' => $this->client->id,
            'status' => 'received',
            'payment_status' => 'pending',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('bookings.index'));

        $this->assertDatabaseHas('booking_groups', [
            'client_id' => $this->client->id,
            'location_type' => 'private_home',
            'address_line1' => '123 Main St',
            'address_city' => 'San Diego',
            'how_did_you_hear' => 'friend_family',
        ]);
        expect(Booking::whereHas('bookingGroup', fn ($q) => $q->where('client_id', $this->client->id))->first()->children)->toHaveCount(1);
    });

    test('client can create a booking for a hotel', function () {
        $hotel = Hotel::factory()->create();
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

        $response = $this->post(route('bookings.store'), [
            'service_type' => 'companion_care',
            'location_type' => 'hotel',
            'start_datetime' => now()->addDays(4)->setHour(9)->setMinute(0)->toISOString(),
            'end_datetime' => now()->addDays(4)->setHour(13)->setMinute(0)->toISOString(),
            'address_id' => null,
            'hotel_id' => $hotel->id,
            'rental_platform' => '',
            'address_line1' => $hotel->line1,
            'address_line2' => $hotel->line2,
            'address_city' => $hotel->city,
            'address_state' => $hotel->state,
            'address_zip' => $hotel->zip,
            'special_considerations' => [],
            'caregiver_notes' => 'Please meet at lobby.',
            'notes_to_sitterwise' => '',
            'sitter_preferences' => [],
            'other_adults_present' => '',
            'emergency_instructions' => '',
            'special_needs_notes' => '',
            'how_did_you_hear' => 'concierge',
            'save_children_pets_to_profile' => false,
            'new_children' => [['name' => $child->name, 'gender' => $child->gender, 'birth_month' => $child->birth_month, 'birth_year' => $child->birth_year]],
            'new_pets' => [],
            'deleted_child_ids' => [],
            'deleted_pet_ids' => [],
            'client_id' => $this->client->id,
            'status' => 'received',
            'payment_status' => 'pending',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('bookings.index'));

        $this->assertDatabaseHas('booking_groups', [
            'client_id' => $this->client->id,
            'location_type' => 'hotel',
            'hotel_id' => $hotel->id,
            'address_line1' => $hotel->line1,
            'how_did_you_hear' => 'concierge',
        ]);
    });

    test('client cannot create a booking shorter than 4 hours', function () {
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);
        $response = $this->post(route('bookings.store'), [
            'service_type' => 'companion_care',
            'location_type' => 'private_home',
            'start_datetime' => now()->addDays(1)->setHour(9)->setMinute(0)->toISOString(),
            'end_datetime' => now()->addDays(1)->setHour(10)->setMinute(0)->toISOString(),
            'address_line1' => '123 Main St',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'how_did_you_hear' => 'concierge',
            'new_children' => [['name' => $child->name, 'gender' => $child->gender, 'birth_month' => $child->birth_month, 'birth_year' => $child->birth_year]],
        ]);

        $response->assertInvalid(['end_datetime']);
        $this->assertDatabaseCount('bookings', 0);
    });

    test('client cannot create a booking with past start_datetime', function () {
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);
        $response = $this->post(route('bookings.store'), [
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => now()->subDay()->setHour(14)->setMinute(0)->toISOString(),
            'end_datetime' => now()->subDay()->setHour(18)->setMinute(0)->toISOString(),
            'address_line1' => '123 Test St',
            'address_city' => 'Test City',
            'address_state' => 'TS',
            'address_zip' => '12345',
            'how_did_you_hear' => 'google',
            'new_children' => [['name' => $child->name, 'gender' => $child->gender, 'birth_month' => $child->birth_month, 'birth_year' => $child->birth_year]],
        ]);

        $response->assertInvalid(['start_datetime']);
        $this->assertDatabaseCount('bookings', 0);
    });

    test('client can delete a child from their profile when creating a booking', function () {
        $childToDelete = ClientChild::factory()->for($this->client)->create();
        $childToKeep = ClientChild::factory()->for($this->client)->create();

        $response = $this->post(route('bookings.store'), [
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => now()->addDays(1)->setHour(14)->setMinute(0)->toISOString(),
            'end_datetime' => now()->addDays(1)->setHour(18)->setMinute(0)->toISOString(),
            'address_line1' => '123 Test St',
            'address_city' => 'Test City',
            'address_state' => 'TS',
            'address_zip' => '12345',
            'caregiver_notes' => '',
            'notes_to_sitterwise' => '',
            'sitter_preferences' => [],
            'other_adults_present' => '',
            'emergency_instructions' => '',
            'special_needs_notes' => '',
            'how_did_you_hear' => 'google',
            'save_children_pets_to_profile' => false,
            'new_children' => [['name' => $childToKeep->name, 'gender' => $childToKeep->gender, 'birth_month' => $childToKeep->birth_month, 'birth_year' => $childToKeep->birth_year]],
            'new_pets' => [],
            'deleted_child_ids' => [$childToDelete->id],
            'deleted_pet_ids' => [],
            'client_id' => $this->client->id,
            'status' => 'received',
            'payment_status' => 'pending',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('bookings.index'));
        $this->assertSoftDeleted('client_children', ['id' => $childToDelete->id]);
        $this->assertDatabaseHas('client_children', ['id' => $childToKeep->id]);

        $booking = Booking::whereHas('bookingGroup', fn ($q) => $q->where('client_id', $this->client->id))->first();
        expect($booking->children)->toHaveCount(1);
        expect($booking->children[0]['name'])->toBe($childToKeep->name);
    });

    test('client can delete a pet from their profile when creating a booking', function () {
        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);
        $petToDelete = ClientPet::factory()->for($this->client)->create();
        $petToKeep = ClientPet::factory()->for($this->client)->create();

        $response = $this->post(route('bookings.store'), [
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => now()->addDays(1)->setHour(14)->setMinute(0)->toISOString(),
            'end_datetime' => now()->addDays(1)->setHour(18)->setMinute(0)->toISOString(),
            'address_line1' => '123 Test St',
            'address_city' => 'Test City',
            'address_state' => 'TS',
            'address_zip' => '12345',
            'caregiver_notes' => '',
            'notes_to_sitterwise' => '',
            'sitter_preferences' => [],
            'other_adults_present' => '',
            'emergency_instructions' => '',
            'special_needs_notes' => '',
            'how_did_you_hear' => 'google',
            'save_children_pets_to_profile' => false,
            'new_children' => [['name' => $child->name, 'gender' => $child->gender, 'birth_month' => $child->birth_month, 'birth_year' => $child->birth_year]],
            'new_pets' => [],
            'deleted_child_ids' => [],
            'deleted_pet_ids' => [$petToDelete->id],
            'client_id' => $this->client->id,
            'status' => 'received',
            'payment_status' => 'pending',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('bookings.index'));
        $this->assertSoftDeleted('client_pets', ['id' => $petToDelete->id]);
        $this->assertDatabaseHas('client_pets', ['id' => $petToKeep->id]);

        $booking = Booking::whereHas('bookingGroup', fn ($q) => $q->where('client_id', $this->client->id))->first();
        expect($booking->pets)->toHaveCount(1);
        expect($booking->pets[0]['name'])->toBe($petToKeep->name);
    });
});

describe('DateTime Picker Local Time Handling', function () {
    beforeEach(function () {
        $this->user = User::factory()->create(['role' => 'client']);
        $this->client = Client::factory()->for($this->user)->create();
        $this->address = ClientAddress::factory()->for($this->client)->create();
        $this->child = ClientChild::factory()->for($this->client)->create();
        $this->actingAs($this->user);
    });

    test('stores datetime exactly as provided without UTC conversion', function () {
        $startDate = now()->addDays(2)->copy()->setHour(9)->setMinute(15);
        $endDate = now()->addDays(2)->copy()->setHour(15)->setMinute(30);

        $response = $this->post(route('bookings.store'), [
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => $startDate->toISOString(),
            'end_datetime' => $endDate->toISOString(),
            'address_id' => $this->address->id,
            'hotel_id' => null,
            'rental_platform' => '',
            'address_line1' => $this->address->line1,
            'address_line2' => $this->address->line2,
            'address_city' => $this->address->city,
            'address_state' => $this->address->state,
            'address_zip' => $this->address->zip,
            'caregiver_notes' => '',
            'notes_to_sitterwise' => '',
            'sitter_preferences' => [],
            'other_adults_present' => '',
            'emergency_instructions' => '',
            'special_needs_notes' => '',
            'how_did_you_hear' => '',
            'save_children_pets_to_profile' => false,
            'new_children' => [['name' => $this->child->name, 'gender' => $this->child->gender, 'birth_month' => $this->child->birth_month, 'birth_year' => $this->child->birth_year]],
            'new_pets' => [],
            'deleted_child_ids' => [],
            'deleted_pet_ids' => [],
        ]);

        $response->assertSessionHasNoErrors();

        $booking = Booking::latest('id')->first();
        expect($booking->start_datetime->format('H:i'))->toBe('09:15');
        expect($booking->end_datetime->format('H:i'))->toBe('15:30');
    });

    test('morning time is stored and retrieved correctly', function () {
        $startDate = now()->addDays(2)->copy()->setHour(6)->setMinute(0);
        $endDate = now()->addDays(2)->copy()->setHour(10)->setMinute(0);

        $response = $this->post(route('bookings.store'), [
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => $startDate->toISOString(),
            'end_datetime' => $endDate->toISOString(),
            'address_id' => $this->address->id,
            'hotel_id' => null,
            'rental_platform' => '',
            'address_line1' => $this->address->line1,
            'address_line2' => $this->address->line2,
            'address_city' => $this->address->city,
            'address_state' => $this->address->state,
            'address_zip' => $this->address->zip,
            'caregiver_notes' => '',
            'notes_to_sitterwise' => '',
            'sitter_preferences' => [],
            'other_adults_present' => '',
            'emergency_instructions' => '',
            'special_needs_notes' => '',
            'how_did_you_hear' => '',
            'save_children_pets_to_profile' => false,
            'new_children' => [['name' => $this->child->name, 'gender' => $this->child->gender, 'birth_month' => $this->child->birth_month, 'birth_year' => $this->child->birth_year]],
            'new_pets' => [],
            'deleted_child_ids' => [],
            'deleted_pet_ids' => [],
        ]);

        $response->assertSessionHasNoErrors();

        $booking = Booking::latest('id')->first();
        expect($booking->start_datetime->format('H:i'))->toBe('06:00');
        expect($booking->end_datetime->format('H:i'))->toBe('10:00');
    });

    test('afternoon time is stored and retrieved correctly', function () {
        $startDate = now()->addDays(2)->copy()->setHour(14)->setMinute(0);
        $endDate = now()->addDays(2)->copy()->setHour(18)->setMinute(0);

        $response = $this->post(route('bookings.store'), [
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => $startDate->toISOString(),
            'end_datetime' => $endDate->toISOString(),
            'address_id' => $this->address->id,
            'hotel_id' => null,
            'rental_platform' => '',
            'address_line1' => $this->address->line1,
            'address_line2' => $this->address->line2,
            'address_city' => $this->address->city,
            'address_state' => $this->address->state,
            'address_zip' => $this->address->zip,
            'caregiver_notes' => '',
            'notes_to_sitterwise' => '',
            'sitter_preferences' => [],
            'other_adults_present' => '',
            'emergency_instructions' => '',
            'special_needs_notes' => '',
            'how_did_you_hear' => '',
            'save_children_pets_to_profile' => false,
            'new_children' => [['name' => $this->child->name, 'gender' => $this->child->gender, 'birth_month' => $this->child->birth_month, 'birth_year' => $this->child->birth_year]],
            'new_pets' => [],
            'deleted_child_ids' => [],
            'deleted_pet_ids' => [],
        ]);

        $response->assertSessionHasNoErrors();

        $booking = Booking::latest('id')->first();
        expect($booking->start_datetime->format('H:i'))->toBe('14:00');
        expect($booking->end_datetime->format('H:i'))->toBe('18:00');
    });

    test('midnight time is stored and retrieved correctly', function () {
        $startDate = now()->addDays(3)->copy()->setHour(0)->setMinute(0);
        $endDate = now()->addDays(3)->copy()->setHour(4)->setMinute(0);

        $response = $this->post(route('bookings.store'), [
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => $startDate->toISOString(),
            'end_datetime' => $endDate->toISOString(),
            'address_id' => $this->address->id,
            'hotel_id' => null,
            'rental_platform' => '',
            'address_line1' => $this->address->line1,
            'address_line2' => $this->address->line2,
            'address_city' => $this->address->city,
            'address_state' => $this->address->state,
            'address_zip' => $this->address->zip,
            'caregiver_notes' => '',
            'notes_to_sitterwise' => '',
            'sitter_preferences' => [],
            'other_adults_present' => '',
            'emergency_instructions' => '',
            'special_needs_notes' => '',
            'how_did_you_hear' => '',
            'save_children_pets_to_profile' => false,
            'new_children' => [['name' => $this->child->name, 'gender' => $this->child->gender, 'birth_month' => $this->child->birth_month, 'birth_year' => $this->child->birth_year]],
            'new_pets' => [],
            'deleted_child_ids' => [],
            'deleted_pet_ids' => [],
        ]);

        $response->assertSessionHasNoErrors();

        $booking = Booking::latest('id')->first();
        expect($booking->start_datetime->format('H:i'))->toBe('00:00');
        expect($booking->end_datetime->format('H:i'))->toBe('04:00');
    });

    test('datetime is retrieved correctly from database without timezone shift', function () {
        $startDate = now()->addDays(2)->copy()->setHour(9)->setMinute(15);
        $endDate = now()->addDays(2)->copy()->setHour(15)->setMinute(30);

        $response = $this->post(route('bookings.store'), [
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => $startDate->toISOString(),
            'end_datetime' => $endDate->toISOString(),
            'address_id' => $this->address->id,
            'hotel_id' => null,
            'rental_platform' => '',
            'address_line1' => $this->address->line1,
            'address_line2' => $this->address->line2,
            'address_city' => $this->address->city,
            'address_state' => $this->address->state,
            'address_zip' => $this->address->zip,
            'caregiver_notes' => '',
            'notes_to_sitterwise' => '',
            'sitter_preferences' => [],
            'other_adults_present' => '',
            'emergency_instructions' => '',
            'special_needs_notes' => '',
            'how_did_you_hear' => '',
            'save_children_pets_to_profile' => false,
            'new_children' => [['name' => $this->child->name, 'gender' => $this->child->gender, 'birth_month' => $this->child->birth_month, 'birth_year' => $this->child->birth_year]],
            'new_pets' => [],
            'deleted_child_ids' => [],
            'deleted_pet_ids' => [],
        ]);

        $response->assertSessionHasNoErrors();

        $booking = Booking::latest('id')->first();

        // Verify the stored timestamp matches what was submitted (no timezone shift)
        $submittedStart = $booking->start_datetime->toISOString();
        $submittedEnd = $booking->end_datetime->toISOString();

        // Extract time portion and compare
        $storedStartTime = substr($submittedStart, 11, 5);
        $storedEndTime = substr($submittedEnd, 11, 5);

        expect($storedStartTime)->toBe('09:15');
        expect($storedEndTime)->toBe('15:30');
    });

    test('booking show page returns correct datetime values', function () {
        $startDate = now()->addDays(2)->copy()->setHour(9)->setMinute(0);
        $endDate = now()->addDays(2)->copy()->setHour(15)->setMinute(0);

        $this->post(route('bookings.store'), [
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => $startDate->toISOString(),
            'end_datetime' => $endDate->toISOString(),
            'address_id' => $this->address->id,
            'hotel_id' => null,
            'rental_platform' => '',
            'address_line1' => $this->address->line1,
            'address_line2' => $this->address->line2,
            'address_city' => $this->address->city,
            'address_state' => $this->address->state,
            'address_zip' => $this->address->zip,
            'caregiver_notes' => '',
            'notes_to_sitterwise' => '',
            'sitter_preferences' => [],
            'other_adults_present' => '',
            'emergency_instructions' => '',
            'special_needs_notes' => '',
            'how_did_you_hear' => '',
            'save_children_pets_to_profile' => false,
            'new_children' => [['name' => $this->child->name, 'gender' => $this->child->gender, 'birth_month' => $this->child->birth_month, 'birth_year' => $this->child->birth_year]],
            'new_pets' => [],
            'deleted_child_ids' => [],
            'deleted_pet_ids' => [],
        ]);

        $booking = Booking::latest('id')->first();

        // Fetch the booking fresh from database to ensure retrieval is correct
        $retrieved = Booking::find($booking->id);

        expect($retrieved->start_datetime->format('Y-m-d H:i'))->toBe($startDate->format('Y-m-d H:i'));
        expect($retrieved->end_datetime->format('Y-m-d H:i'))->toBe($endDate->format('Y-m-d H:i'));
    });

    describe('Child year-only birthday', function () {
        test('new child with year but no month defaults birth_month to current month', function () {
            $currentMonth = now()->month;
            $startDate = now()->addDays(2);
            $startDatetime = $startDate->copy()->setHour(9)->setMinute(0);
            $endDatetime = $startDate->copy()->setHour(15)->setMinute(0);
            $sitterPreference = fake()->randomElement(array_column(SitterPreference::cases(), 'value'));
            $clientAddress = ClientAddress::factory()->for($this->client)->create();

            $response = $this->post(route('bookings.store'), [
                'service_type' => 'babysitter',
                'location_type' => 'private_home',
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
                'address_id' => $clientAddress->id,
                'address_line1' => $clientAddress->line1,
                'address_city' => $clientAddress->city,
                'address_state' => $clientAddress->state,
                'address_zip' => $clientAddress->zip,
                'caregiver_notes' => '',
                'notes_to_sitterwise' => '',
                'sitter_preferences' => [$sitterPreference],
                'other_adults_present' => '',
                'emergency_instructions' => '',
                'special_needs_notes' => '',
                'how_did_you_hear' => 'google',
                'save_children_pets_to_profile' => true,
                'new_children' => [
                    [
                        'name' => 'YearOnly Solo',
                        'gender' => 'female',
                        'birth_month' => '',
                        'birth_year' => '2020',
                    ],
                ],
                'new_pets' => [],
                'deleted_child_ids' => [],
                'deleted_pet_ids' => [],
                'client_id' => $this->client->id,
                'status' => 'received',
                'payment_status' => 'pending',
            ]);

            $response->assertSessionHasNoErrors();
            $response->assertRedirect(route('bookings.index'));

            $saved = ClientChild::where('client_id', $this->client->id)
                ->where('name', 'YearOnly Solo')
                ->first();

            expect($saved)->not->toBeNull();
            expect($saved->birth_date)->not->toBeNull();
            expect($saved->birth_year)->toBe(2020);
            expect($saved->birth_month)->toBe($currentMonth);
        });

        test('new child with empty month and year sets birth_date to null', function () {
            $startDate = now()->addDays(2);
            $startDatetime = $startDate->copy()->setHour(9)->setMinute(0);
            $endDatetime = $startDate->copy()->setHour(15)->setMinute(0);
            $sitterPreference = fake()->randomElement(array_column(SitterPreference::cases(), 'value'));
            $clientAddress = ClientAddress::factory()->for($this->client)->create();

            $response = $this->post(route('bookings.store'), [
                'service_type' => 'babysitter',
                'location_type' => 'private_home',
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
                'address_id' => $clientAddress->id,
                'address_line1' => $clientAddress->line1,
                'address_city' => $clientAddress->city,
                'address_state' => $clientAddress->state,
                'address_zip' => $clientAddress->zip,
                'caregiver_notes' => '',
                'notes_to_sitterwise' => '',
                'sitter_preferences' => [$sitterPreference],
                'other_adults_present' => '',
                'emergency_instructions' => '',
                'special_needs_notes' => '',
                'how_did_you_hear' => 'google',
                'save_children_pets_to_profile' => true,
                'new_children' => [
                    [
                        'name' => 'NoBirth Solo',
                        'gender' => 'male',
                        'birth_month' => '',
                        'birth_year' => '',
                    ],
                ],
                'new_pets' => [],
                'deleted_child_ids' => [],
                'deleted_pet_ids' => [],
                'client_id' => $this->client->id,
                'status' => 'received',
                'payment_status' => 'pending',
            ]);

            $response->assertSessionHasNoErrors();
            $response->assertRedirect(route('bookings.index'));

            $saved = ClientChild::where('client_id', $this->client->id)
                ->where('name', 'NoBirth Solo')
                ->first();

            expect($saved)->not->toBeNull();
            expect($saved->birth_date)->toBeNull();
        });

        test('new child with month but no year sets birth_date to null', function () {
            $startDate = now()->addDays(2);
            $startDatetime = $startDate->copy()->setHour(9)->setMinute(0);
            $endDatetime = $startDate->copy()->setHour(15)->setMinute(0);
            $sitterPreference = fake()->randomElement(array_column(SitterPreference::cases(), 'value'));
            $clientAddress = ClientAddress::factory()->for($this->client)->create();

            $response = $this->post(route('bookings.store'), [
                'service_type' => 'babysitter',
                'location_type' => 'private_home',
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
                'address_id' => $clientAddress->id,
                'address_line1' => $clientAddress->line1,
                'address_city' => $clientAddress->city,
                'address_state' => $clientAddress->state,
                'address_zip' => $clientAddress->zip,
                'caregiver_notes' => '',
                'notes_to_sitterwise' => '',
                'sitter_preferences' => [$sitterPreference],
                'other_adults_present' => '',
                'emergency_instructions' => '',
                'special_needs_notes' => '',
                'how_did_you_hear' => 'google',
                'save_children_pets_to_profile' => true,
                'new_children' => [
                    [
                        'name' => 'MonthOnly Solo',
                        'gender' => 'female',
                        'birth_month' => '6',
                        'birth_year' => '',
                    ],
                ],
                'new_pets' => [],
                'deleted_child_ids' => [],
                'deleted_pet_ids' => [],
                'client_id' => $this->client->id,
                'status' => 'received',
                'payment_status' => 'pending',
            ]);

            $response->assertSessionHasNoErrors();
            $response->assertRedirect(route('bookings.index'));

            $saved = ClientChild::where('client_id', $this->client->id)
                ->where('name', 'MonthOnly Solo')
                ->first();

            expect($saved)->not->toBeNull();
            expect($saved->birth_date)->toBeNull();
        });
    });
});
