<?php

use App\Models\Availability;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\Hotel;
use App\Models\PricingRule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can be instantiated', function () {
    $booking = Booking::factory()->make();

    $this->assertInstanceOf(Booking::class, $booking);
});

test('has correct fillable fields', function () {
    $startDatetime = now()->addDays(rand(1, 30))->setHour(rand(8, 18))->setMinute(0);
    $endDatetime = (clone $startDatetime)->addHours(4);

    $client = Client::factory()->create();
    $group = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'service_type' => 'babysitter',
        'location_type' => 'hotel',
    ]);
    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'start_datetime' => $startDatetime,
        'end_datetime' => $endDatetime,
        'status' => 'received',
        'payment_status' => 'pending',
        'total_working_hour' => 4,
    ]);

    $this->assertEquals($client->id, $booking->client_id);
    $this->assertEquals('babysitter', $booking->service_type);
    $this->assertEquals('hotel', $booking->location_type);
    $this->assertEquals('received', $booking->status);
    $this->assertEquals('pending', $booking->payment_status);
    $this->assertEquals(4, $booking->total_working_hour);
    $this->assertTrue($booking->requires_payment);
});

test('casts attributes correctly', function () {
    $startDateTime = now()->addDays(1);
    $endDateTime = now()->addDays(1)->addHours(4);
    $booking = Booking::factory()->withBookingGroup(fn ($group) => $group->state([
        'requires_payment' => false,
    ]))->create([
        'start_datetime' => $startDateTime,
        'end_datetime' => $endDateTime,
    ]);

    $this->assertInstanceOf(CarbonImmutable::class, $booking->start_datetime);
    $this->assertEquals($startDateTime->timestamp, $booking->start_datetime->timestamp);
    $this->assertInstanceOf(CarbonImmutable::class, $booking->end_datetime);
    $this->assertEquals($endDateTime->timestamp, $booking->end_datetime->timestamp);
    $this->assertEquals($booking->total_service_amount, $booking->charge_to_client + $booking->reimbursement + $booking->bonus);
    $this->assertFalse($booking->requires_payment);
});

test('defines client relationship', function () {
    $client = Client::factory()->create();
    $booking = Booking::factory()->forClient($client)->create();

    $relatedClient = $booking->client;

    $this->assertInstanceOf(Client::class, $relatedClient);
    $this->assertEquals($client->id, $relatedClient->id);
});

test('defines caregiver relationship', function () {
    // Seed required lookup tables for CaregiverFactory's configure() method
    $this->seed(SpecialtyTypeSeeder::class);
    $this->seed(LocationSeeder::class);
    $this->seed(AttributeDefinitionSeeder::class);
    $this->seed(CertificationTypeSeeder::class);

    $user = User::factory()->create();
    $caregiver = Caregiver::factory()->create();
    $booking = Booking::factory()->create(['caregiver_id' => $caregiver->id]);

    $relatedCaregiver = $booking->caregiver;

    $this->assertInstanceOf(Caregiver::class, $relatedCaregiver);
    $this->assertEquals($caregiver->id, $relatedCaregiver->id);
});

test('defines availability relationship', function () {
    // Seed required lookup tables for CaregiverFactory's configure() method
    $this->seed(SpecialtyTypeSeeder::class);
    $this->seed(LocationSeeder::class);
    $this->seed(AttributeDefinitionSeeder::class);
    $this->seed(CertificationTypeSeeder::class);

    $user = User::factory()->create();
    $caregiver = Caregiver::factory()->create();
    $availability = Availability::factory()->create(['caregiver_id' => $caregiver->id]);
    $booking = Booking::factory()->create(['availability_id' => $availability->id]);

    $relatedAvailability = $booking->availability;

    $this->assertInstanceOf(Availability::class, $relatedAvailability);
    $this->assertEquals($availability->id, $relatedAvailability->id);
});

test('defines hotel relationship', function () {
    $hotel = Hotel::factory()->create();
    $group = BookingGroup::factory()->create(['hotel_id' => $hotel->id]);
    $booking = Booking::factory()->create(['booking_group_id' => $group->id]);

    $relatedHotel = $booking->hotel;

    $this->assertInstanceOf(Hotel::class, $relatedHotel);
    $this->assertEquals($hotel->id, $relatedHotel->id);
});

test('defines address relationship', function () {
    $address = ClientAddress::factory()->create();
    $group = BookingGroup::factory()->create(['address_id' => $address->id]);
    $booking = Booking::factory()->create(['booking_group_id' => $group->id]);

    $relatedAddress = $booking->address;

    $this->assertInstanceOf(ClientAddress::class, $relatedAddress);
    $this->assertEquals($address->id, $relatedAddress->id);
});

test('has address fields', function () {
    $group = BookingGroup::factory()->create([
        'address_line1' => '123 Test St',
        'address_line2' => 'Apt 4B',
        'address_city' => 'Test City',
        'address_state' => 'TS',
        'address_zip' => '12345',
    ]);
    $booking = Booking::factory()->create(['booking_group_id' => $group->id]);

    $this->assertEquals('123 Test St', $booking->address_line1);
    $this->assertEquals('Apt 4B', $booking->address_line2);
    $this->assertEquals('Test City', $booking->address_city);
    $this->assertEquals('TS', $booking->address_state);
    $this->assertEquals('12345', $booking->address_zip);
});

test('defines attribute definitions relationship', function () {
    // This relationship is more complex due to the pivot table
    // We'll test that the method exists and returns the correct type
    $booking = Booking::factory()->make();
    $relation = $booking->attributeDefinitions();

    $this->assertInstanceOf(BelongsToMany::class, $relation);
});

test('calculate hourly rate uses matching children count', function () {
    $client = Client::factory()->create();

    PricingRule::factory()->create([
        'service_type' => 'babysitter',
        'number_of_children' => 2,
        'is_for_pets' => false,
        'charge_to_client' => 30.00,
        'paid_to_caregiver' => 20.00,
        'sitterwise_cut' => 10.00,
    ]);

    $group = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'service_type' => 'babysitter',
        'children' => ['child1', 'child2'],
    ]);
    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'status' => 'received',
        'payment_status' => 'pending',
        'total_amount' => 0,
    ]);
    $booking->calculateHourlyRate();

    $this->assertEquals(30.00, $booking->charge_to_client_hourly);
    $this->assertEquals(20.00, $booking->paid_to_caregiver_hourly);
    $this->assertEquals(10.00, $booking->sitterwise_cut_hourly);
});

test('calculate hourly rate falls back to max children when exceeds', function () {
    $client = Client::factory()->create();

    PricingRule::factory()->create([
        'service_type' => 'babysitter',
        'number_of_children' => 4,
        'is_for_pets' => false,
        'charge_to_client' => 50.00,
        'paid_to_caregiver' => 35.00,
        'sitterwise_cut' => 15.00,
    ]);

    $group = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'service_type' => 'babysitter',
        'children' => ['child1', 'child2', 'child3', 'child4', 'child5'],
    ]);
    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'status' => 'received',
        'payment_status' => 'pending',
        'total_amount' => 0,
    ]);
    $booking->calculateHourlyRate();

    $this->assertEquals(50.00, $booking->charge_to_client_hourly);
    $this->assertEquals(35.00, $booking->paid_to_caregiver_hourly);
    $this->assertEquals(15.00, $booking->sitterwise_cut_hourly);
});

test('calculate hourly rate for petsitter with pets', function () {
    $client = Client::factory()->create();

    PricingRule::factory()->create([
        'service_type' => 'petsitter',
        'number_of_children' => 0,
        'is_for_pets' => true,
        'charge_to_client' => 40.00,
        'paid_to_caregiver' => 25.00,
        'sitterwise_cut' => 15.00,
    ]);

    $group = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'service_type' => 'petsitter',
        'pets' => ['dog', 'cat'],
    ]);
    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'status' => 'received',
        'payment_status' => 'pending',
        'total_amount' => 0,
    ]);
    $booking->calculateHourlyRate();

    $this->assertEquals(40.00, $booking->charge_to_client_hourly);
    $this->assertEquals(25.00, $booking->paid_to_caregiver_hourly);
    $this->assertEquals(15.00, $booking->sitterwise_cut_hourly);
});

test('calculate hourly rate for petsitter without pets', function () {
    $client = Client::factory()->create();

    PricingRule::factory()->create([
        'service_type' => 'petsitter',
        'number_of_children' => 0,
        'is_for_pets' => false,
        'charge_to_client' => 35.00,
        'paid_to_caregiver' => 20.00,
        'sitterwise_cut' => 15.00,
    ]);

    $group = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'service_type' => 'petsitter',
        'pets' => null,
    ]);
    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'status' => 'received',
        'payment_status' => 'pending',
        'total_amount' => 0,
    ]);
    $booking->calculateHourlyRate();

    $this->assertEquals(35.00, $booking->charge_to_client_hourly);
    $this->assertEquals(20.00, $booking->paid_to_caregiver_hourly);
    $this->assertEquals(15.00, $booking->sitterwise_cut_hourly);
});

test('calculate hourly rate uses zero children when children null', function () {
    $client = Client::factory()->create();

    PricingRule::factory()->create([
        'service_type' => 'babysitter',
        'number_of_children' => 0,
        'is_for_pets' => false,
        'charge_to_client' => 25.00,
        'paid_to_caregiver' => 15.00,
        'sitterwise_cut' => 10.00,
    ]);

    $group = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'service_type' => 'babysitter',
        'children' => null,
    ]);
    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'status' => 'received',
        'payment_status' => 'pending',
        'total_amount' => 0,
    ]);
    $booking->calculateHourlyRate();

    $this->assertEquals(25.00, $booking->charge_to_client_hourly);
    $this->assertEquals(15.00, $booking->paid_to_caregiver_hourly);
    $this->assertEquals(10.00, $booking->sitterwise_cut_hourly);
});

test('calculate hourly rate defaults to zero when no match', function () {
    $client = Client::factory()->create();

    $group = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'service_type' => 'babysitter',
        'children' => ['child1'],
    ]);
    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'status' => 'received',
        'payment_status' => 'pending',
        'total_amount' => 0,
    ]);
    $booking->calculateHourlyRate();

    $this->assertEquals(0.00, $booking->charge_to_client_hourly);
    $this->assertEquals(0.00, $booking->paid_to_caregiver_hourly);
    $this->assertEquals(0.00, $booking->sitterwise_cut_hourly);
});

test('calculate hourly rate for group_childcare_invoiced with null children falls back to pricing rule', function () {
    $client = Client::factory()->create();

    PricingRule::factory()->create([
        'service_type' => 'group_childcare_invoiced',
        'number_of_children' => 5,
        'charge_to_client' => 36.00,
        'paid_to_caregiver' => 23.00,
        'sitterwise_cut' => 0.00,
    ]);

    $group = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'service_type' => 'group_childcare_invoiced',
        'children' => null,
    ]);
    $booking = Booking::factory()->create(['booking_group_id' => $group->id]);
    $booking->calculateHourlyRate();

    $this->assertEquals(36.00, $booking->charge_to_client_hourly);
    $this->assertEquals(23.00, $booking->paid_to_caregiver_hourly);
    $this->assertEquals(0.00, $booking->sitterwise_cut_hourly);
});

test('calculate hourly rate for corporate_invoiced with null children falls back to pricing rule', function () {
    $client = Client::factory()->create();

    PricingRule::factory()->create([
        'service_type' => 'corporate_invoiced',
        'number_of_children' => 1,
        'charge_to_client' => 36.00,
        'paid_to_caregiver' => 23.00,
        'sitterwise_cut' => 0.00,
    ]);

    $group = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'service_type' => 'corporate_invoiced',
        'children' => null,
    ]);
    $booking = Booking::factory()->create(['booking_group_id' => $group->id]);
    $booking->calculateHourlyRate();

    $this->assertEquals(36.00, $booking->charge_to_client_hourly);
    $this->assertEquals(23.00, $booking->paid_to_caregiver_hourly);
    $this->assertEquals(0.00, $booking->sitterwise_cut_hourly);
});

test('calculate hourly rate for comped with null children falls back to pricing rule', function () {
    $client = Client::factory()->create();

    PricingRule::factory()->create([
        'service_type' => 'comped',
        'number_of_children' => 1,
        'charge_to_client' => 0.00,
        'paid_to_caregiver' => 23.00,
        'sitterwise_cut' => 0.00,
    ]);

    $group = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'service_type' => 'comped',
        'children' => null,
    ]);
    $booking = Booking::factory()->create(['booking_group_id' => $group->id]);
    $booking->calculateHourlyRate();

    $this->assertEquals(0.00, $booking->charge_to_client_hourly);
    $this->assertEquals(23.00, $booking->paid_to_caregiver_hourly);
    $this->assertEquals(0.00, $booking->sitterwise_cut_hourly);
});

test('calculate total amount does not modify financials for paid booking', function () {
    $client = Client::factory()->create();
    $group = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'service_type' => 'babysitter',
    ]);
    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'status' => 'paid',
        'charge_to_client' => 100.00,
        'paid_to_caregiver' => 70.00,
        'sitterwise_cut' => 30.00,
        'total_service_amount' => 100.00,
        'total_amount' => 110.00,
        'paid_to_caregiver_total' => 80.00,
        'charge_to_client_hourly' => 0,
        'total_working_hour' => 0,
    ]);

    $booking->calculateTotalAmount();

    $this->assertEquals(100.00, $booking->charge_to_client);
    $this->assertEquals(70.00, $booking->paid_to_caregiver);
    $this->assertEquals(30.00, $booking->sitterwise_cut);
    $this->assertEquals(100.00, $booking->total_service_amount);
    $this->assertEquals(110.00, $booking->total_amount);
    $this->assertEquals(80.00, $booking->paid_to_caregiver_total);
});

test('calculate total amount zeros financials for cancelled booking', function () {
    $client = Client::factory()->create();
    $group = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'service_type' => 'babysitter',
    ]);
    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'status' => 'cancelled',
        'charge_to_client' => 100.00,
        'paid_to_caregiver' => 70.00,
        'sitterwise_cut' => 30.00,
        'total_service_amount' => 100.00,
        'total_amount' => 110.00,
        'paid_to_caregiver_total' => 80.00,
    ]);

    $booking->calculateTotalAmount();

    $this->assertEquals(0.00, $booking->charge_to_client);
    $this->assertEquals(0.00, $booking->paid_to_caregiver);
    $this->assertEquals(0.00, $booking->sitterwise_cut);
    $this->assertEquals(0.00, $booking->total_service_amount);
    $this->assertEquals(0.00, $booking->total_amount);
    $this->assertEquals(0.00, $booking->paid_to_caregiver_total);
});

test('calculate total amount includes paid_to_caregiver_total for completed booking', function () {
    $client = Client::factory()->create();
    $group = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'service_type' => 'babysitter',
    ]);
    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'status' => 'completed',
        'start_datetime' => now()->subHours(10),
        'end_datetime' => now()->subHours(5),
        'reimbursement' => 10.00,
        'bonus' => 5.00,
        'tip' => 3.00,
    ]);

    $booking->paid_to_caregiver_hourly = 20.00;
    $booking->charge_to_client_hourly = 30.00;
    $booking->sitterwise_cut_hourly = 10.00;
    $booking->calculateTotalAmount();

    $expectedCaregiver = 20.00 * 5; // 100
    $expectedTotal = $expectedCaregiver + 10.00 + 5.00 + 3.00; // 118

    $this->assertEquals(100.00, $booking->paid_to_caregiver);
    $this->assertEquals(150.00, $booking->charge_to_client);
    $this->assertEquals(50.00, $booking->sitterwise_cut);
    $this->assertEquals(165.00, $booking->total_service_amount);
    $this->assertEquals(168.00, $booking->total_amount);
    $this->assertEquals(118.00, $booking->paid_to_caregiver_total);
});

test('calculate total amount does not modify financials for past-dated confirmed booking', function () {
    $client = Client::factory()->create();
    $group = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'service_type' => 'babysitter',
    ]);
    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'status' => 'confirmed',
        'start_datetime' => now()->subDays(2),
        'end_datetime' => now()->subDays(2)->addHours(4),
        'charge_to_client' => 100.00,
        'paid_to_caregiver' => 70.00,
        'sitterwise_cut' => 30.00,
        'total_service_amount' => 100.00,
        'total_amount' => 110.00,
        'charge_to_client_hourly' => 0,
        'total_working_hour' => 0,
    ]);

    $booking->calculateTotalAmount();

    $this->assertEquals(100.00, $booking->charge_to_client);
    $this->assertEquals(70.00, $booking->paid_to_caregiver);
    $this->assertEquals(30.00, $booking->sitterwise_cut);
    $this->assertEquals(100.00, $booking->total_service_amount);
    $this->assertEquals(110.00, $booking->total_amount);
});

/**
 * @return array{0: BookingGroup, 1: Hotel}
 */
function hotelBookingGroup(?float $resortFee, string $locationType = 'hotel'): array
{
    $client = Client::factory()->create();
    $hotel = Hotel::factory()->create(['resort_fee' => $resortFee]);

    PricingRule::factory()->create([
        'service_type' => 'babysitter',
        'number_of_children' => 0,
        'charge_to_client' => 30.00,
        'paid_to_caregiver' => 20.00,
        'sitterwise_cut' => 10.00,
    ]);

    $group = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'service_type' => 'babysitter',
        'location_type' => $locationType,
        'hotel_id' => $locationType === 'hotel' ? $hotel->id : null,
        'children' => [],
    ]);

    return [$group, $hotel];
}

function makeHotelBooking(int $groupId, array $overrides = []): Booking
{
    return Booking::factory()->create(array_merge([
        'booking_group_id' => $groupId,
        'status' => 'confirmed',
        'start_datetime' => now()->addDay()->setHour(9)->setMinute(0),
        'end_datetime' => now()->addDay()->setHour(13)->setMinute(0),
        'total_working_hour' => 4,
        'reimbursement' => 0,
        'bonus' => 0,
        'tip' => 0,
    ], $overrides));
}

test('auto-populates hotel_fee from the hotel resort fee on create and bills only the client', function () {
    [$group] = hotelBookingGroup(25.00);

    $booking = makeHotelBooking($group->id);

    // charge_to_client = 30 * 4 = 120; total_service_amount = 120 + 25 (hotel fee)
    expect((float) $booking->hotel_fee)->toBe(25.00)
        ->and((float) $booking->charge_to_client)->toBe(120.00)
        ->and((float) $booking->total_service_amount)->toBe(145.00)
        ->and((float) $booking->total_amount)->toBe(145.00)
        // The hotel fee is NOT paid to the caregiver and does not change the cut.
        ->and((float) $booking->paid_to_caregiver_total)->toBe(80.00)
        ->and((float) $booking->sitterwise_cut)->toBe(40.00);
});

test('does not populate hotel_fee for a non-hotel booking', function () {
    [$group] = hotelBookingGroup(25.00, 'private_home');

    $booking = makeHotelBooking($group->id);

    expect($booking->hotel_fee)->toBeNull()
        ->and((float) $booking->total_service_amount)->toBe(120.00);
});

test('does not populate hotel_fee when the hotel resort fee is zero or null', function (?float $resortFee) {
    [$group] = hotelBookingGroup($resortFee);

    $booking = makeHotelBooking($group->id);

    expect($booking->hotel_fee)->toBeNull()
        ->and((float) $booking->total_service_amount)->toBe(120.00);
})->with([0.0, null]);

test('does not overwrite a hotel_fee that was already set on create', function () {
    [$group] = hotelBookingGroup(25.00);

    $booking = makeHotelBooking($group->id, ['hotel_fee' => 99.00]);

    expect((float) $booking->hotel_fee)->toBe(99.00)
        ->and((float) $booking->total_service_amount)->toBe(219.00);
});

test('includes hotel_fee in total_service_amount via calculateTotalAmount', function () {
    $client = Client::factory()->create();
    $group = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'service_type' => 'babysitter',
    ]);
    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'status' => 'completed',
        'start_datetime' => now()->subHours(9),
        'end_datetime' => now()->subHours(5),
        'reimbursement' => 10.00,
        'bonus' => 5.00,
        'tip' => 3.00,
        'hotel_fee' => 15.00,
    ]);

    $booking->paid_to_caregiver_hourly = 20.00;
    $booking->charge_to_client_hourly = 30.00;
    $booking->sitterwise_cut_hourly = 10.00;
    $booking->calculateTotalAmount();

    // service = 30*4 + 10 + 5 + 15 (hotel) = 150; total = 153 with tip.
    expect((float) $booking->total_service_amount)->toBe(150.00)
        ->and((float) $booking->total_amount)->toBe(153.00)
        // Caregiver payout unchanged by the hotel fee: 80 + 10 + 5 + 3 = 98.
        ->and((float) $booking->paid_to_caregiver_total)->toBe(98.00);
});

test('zeros hotel_fee for a cancelled booking', function () {
    $client = Client::factory()->create();
    $group = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'service_type' => 'babysitter',
    ]);
    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'status' => 'cancelled',
        'hotel_fee' => 25.00,
        'total_service_amount' => 100.00,
    ]);

    $booking->calculateTotalAmount();

    expect((float) $booking->hotel_fee)->toBe(0.0)
        ->and((float) $booking->total_service_amount)->toBe(0.0);
});
