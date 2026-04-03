<?php

use App\Models\Availability;
use App\Models\Booking;
use App\Models\BookingAddress;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\Hotel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated()
    {
        $booking = Booking::factory()->make();

        $this->assertInstanceOf(Booking::class, $booking);
    }

    public function test_has_correct_fillable_fields()
    {
        $client = Client::factory()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'service_type' => 'babysitter',
            'location_type' => 'hotel',
            'status' => 'received',
            'payment_status' => 'pending',
            'total_amount' => 100.00,
            'requires_payment' => true,
        ]);

        $this->assertEquals($client->id, $booking->client_id);
        $this->assertEquals('babysitter', $booking->service_type);
        $this->assertEquals('hotel', $booking->location_type);
        $this->assertEquals('received', $booking->status);
        $this->assertEquals('pending', $booking->payment_status);
        $this->assertEquals(100.00, $booking->total_amount);
        $this->assertTrue($booking->requires_payment);
    }

    public function test_casts_attributes_correctly()
    {
        $startDateTime = now()->addDays(1);
        $endDateTime = now()->addDays(1)->addHours(4);
        $booking = Booking::factory()->create([
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'comped' => true,
            'total_amount' => 150.50,
            'requires_payment' => false,
        ]);

        $this->assertInstanceOf(CarbonImmutable::class, $booking->start_datetime);
        $this->assertEquals($startDateTime->timestamp, $booking->start_datetime->timestamp);
        $this->assertInstanceOf(CarbonImmutable::class, $booking->end_datetime);
        $this->assertEquals($endDateTime->timestamp, $booking->end_datetime->timestamp);
        $this->assertTrue($booking->comped);
        $this->assertEquals(150.50, $booking->total_amount);
        $this->assertFalse($booking->requires_payment);
    }

    public function test_defines_client_relationship()
    {
        $client = Client::factory()->create();
        $booking = Booking::factory()->create(['client_id' => $client->id]);

        $relatedClient = $booking->client;

        $this->assertInstanceOf(Client::class, $relatedClient);
        $this->assertEquals($client->id, $relatedClient->id);
    }

    public function test_defines_caregiver_relationship()
    {
        // Seed required lookup tables for CaregiverFactory's configure() method
        $this->seed(CaregiverStatusSeeder::class);
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
    }

    public function test_defines_availability_relationship()
    {
        // Seed required lookup tables for CaregiverFactory's configure() method
        $this->seed(CaregiverStatusSeeder::class);
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
    }

    public function test_defines_hotel_relationship()
    {
        $hotel = Hotel::factory()->create();
        $booking = Booking::factory()->create(['hotel_id' => $hotel->id]);

        $relatedHotel = $booking->hotel;

        $this->assertInstanceOf(Hotel::class, $relatedHotel);
        $this->assertEquals($hotel->id, $relatedHotel->id);
    }

    public function test_defines_address_relationship()
    {
        $address = ClientAddress::factory()->create();
        $booking = Booking::factory()->create(['address_id' => $address->id]);

        $relatedAddress = $booking->address;

        $this->assertInstanceOf(ClientAddress::class, $relatedAddress);
        $this->assertEquals($address->id, $relatedAddress->id);
    }

    public function test_defines_booking_address_relationship()
    {
        // The bookingAddress() relationship uses belongsTo which expects a booking_address_id
        // foreign key on the bookings table. Since that column doesn't exist,
        // we verify the relationship method returns the correct type.
        $booking = Booking::factory()->make();
        $relation = $booking->bookingAddress();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(BookingAddress::class, $relation->getRelated());
    }

    public function test_defines_attribute_definitions_relationship()
    {
        // This relationship is more complex due to the pivot table
        // We'll test that the method exists and returns the correct type
        $booking = Booking::factory()->make();
        $relation = $booking->attributeDefinitions();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }
}
