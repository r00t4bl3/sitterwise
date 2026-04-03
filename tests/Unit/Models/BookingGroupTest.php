<?php

use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated()
    {
        $bookingGroup = BookingGroup::factory()->make();

        $this->assertInstanceOf(BookingGroup::class, $bookingGroup);
    }

    public function test_has_correct_fillable_fields()
    {
        $client = Client::factory()->create();
        $bookingGroup = BookingGroup::factory()->create([
            'client_id' => $client->id,
            'submitted_at' => now(),
            'submission_type' => 'logged_in',
            'is_split' => true,
        ]);

        $this->assertEquals($client->id, $bookingGroup->client_id);
        $this->assertInstanceOf(CarbonImmutable::class, $bookingGroup->submitted_at);
        $this->assertEquals('logged_in', $bookingGroup->submission_type);
        $this->assertTrue($bookingGroup->is_split);
    }

    public function test_casts_submitted_at_as_datetime()
    {
        $bookingGroup = BookingGroup::factory()->create([
            'submitted_at' => '2026-12-25 10:30:00',
        ]);

        $this->assertInstanceOf(CarbonImmutable::class, $bookingGroup->submitted_at);
        $this->assertEquals('2026-12-25', $bookingGroup->submitted_at->toDateString());
    }

    public function test_casts_is_split_as_boolean()
    {
        $bookingGroup = BookingGroup::factory()->create(['is_split' => true]);

        $this->assertTrue($bookingGroup->is_split);
        $this->assertIsBool($bookingGroup->is_split);
    }

    public function test_defines_client_relationship()
    {
        $client = Client::factory()->create();
        $bookingGroup = BookingGroup::factory()->create(['client_id' => $client->id]);

        $this->assertInstanceOf(Client::class, $bookingGroup->client);
        $this->assertEquals($client->id, $bookingGroup->client->id);
    }

    public function test_defines_bookings_relationship()
    {
        $bookingGroup = BookingGroup::factory()->create();
        $booking = Booking::factory()->create(['booking_group_id' => $bookingGroup->id]);

        $this->assertTrue($bookingGroup->bookings->contains($booking));
    }
}
