<?php

use App\Models\Hotel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated()
    {
        $hotel = Hotel::factory()->make();

        $this->assertInstanceOf(Hotel::class, $hotel);
    }

    public function test_has_correct_fillable_fields()
    {
        $hotel = Hotel::factory()->create([
            'name' => 'Test Hotel',
            'line1' => '123 Hotel St',
            'city' => 'San Diego',
            'state' => 'CA',
            'zip' => '92101',
            'hourly_rate' => 25.00,
            'resort_fee' => 15.00,
            'is_active' => true,
        ]);

        $this->assertEquals('Test Hotel', $hotel->name);
        $this->assertEquals('123 Hotel St', $hotel->line1);
        $this->assertEquals('San Diego', $hotel->city);
        $this->assertEquals('CA', $hotel->state);
        $this->assertEquals('92101', $hotel->zip);
        $this->assertEquals(25.00, $hotel->hourly_rate);
        $this->assertEquals(15.00, $hotel->resort_fee);
        $this->assertTrue($hotel->is_active);
    }

    public function test_casts_is_active_as_boolean()
    {
        $hotel = Hotel::factory()->create(['is_active' => true]);

        $this->assertTrue($hotel->is_active);
        $this->assertIsBool($hotel->is_active);
    }

    public function test_casts_hourly_rate_as_decimal()
    {
        $hotel = Hotel::factory()->create(['hourly_rate' => 30.50]);

        $this->assertEquals(30.50, $hotel->hourly_rate);
    }

    public function test_casts_resort_fee_as_decimal()
    {
        $hotel = Hotel::factory()->create(['resort_fee' => 20.75]);

        $this->assertEquals(20.75, $hotel->resort_fee);
    }

    public function test_active_scope_returns_only_active_hotels()
    {
        Hotel::factory()->create(['name' => 'Active Hotel', 'is_active' => true]);
        Hotel::factory()->create(['name' => 'Inactive Hotel', 'is_active' => false]);

        $activeHotels = Hotel::active()->get();

        $this->assertCount(1, $activeHotels);
        $this->assertEquals('Active Hotel', $activeHotels->first()->name);
    }
}
