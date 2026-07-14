<?php

use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ClientChild;
use App\Models\Hotel;
use App\Models\PricingRule;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\PricingRulesTableSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->seed([
        PricingRulesTableSeeder::class,
        AttributeDefinitionSeeder::class,
        CertificationTypeSeeder::class,
        LocationSeeder::class,
        SpecialtyTypeSeeder::class,
    ]);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->client = Client::factory()->create();
    $this->hotel = Hotel::factory()->create();
});

test('an admin can update a booking to a sub-4h window; it saves and bills the 4-hour minimum', function () {
    // The admin Edit Booking picker allows sub-4h ends (the "adjust to earlier"
    // flow); the server must accept it too (no MinimumBookingDuration on the admin
    // update path) while billing floors at the minimum.
    $caregiver = Caregiver::factory()->create();
    $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

    $booking = Booking::factory()
        ->forClient($this->client)
        ->withBookingGroup(fn ($g) => $g->state([
            'service_type' => 'babysitter',
            'location_type' => 'hotel',
            'hotel_id' => $this->hotel->id,
        ]))
        ->create([
            'caregiver_id' => $caregiver->id,
            'status' => 'confirmed',
            'start_datetime' => now()->addDays(2)->setHour(9)->setMinute(0),
            'end_datetime' => now()->addDays(2)->setHour(17)->setMinute(0),
            'pricing_rule_id' => PricingRule::first()?->id,
        ]);

    $start = now()->addDays(2)->setHour(9)->setMinute(0)->setSecond(0);
    $end = (clone $start)->addHours(2); // 2-hour window — below the 4h minimum

    $this->actingAs($this->admin)
        ->patch(route('bookings.update', $booking), [
            'client_id' => $this->client->id,
            'service_type' => 'babysitter',
            'location_type' => 'hotel',
            'hotel_id' => $this->hotel->id,
            'start_datetime' => $start->toISOString(),
            'end_datetime' => $end->toISOString(),
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'new_children' => [
                ['name' => $child->name, 'gender' => $child->gender, 'birth_month' => $child->birth_month, 'birth_year' => $child->birth_year],
            ],
            'address_line1' => '123 Hotel Way',
            'address_city' => 'Los Angeles',
            'address_state' => 'CA',
            'address_zip' => '90001',
        ])
        ->assertSessionHasNoErrors();

    $booking->refresh();

    // True 2h saved; charge floored to the 4h minimum.
    expect((float) $booking->total_working_hour)->toBe(2.0)
        ->and((float) $booking->charge_to_client)
        ->toBe(round((float) $booking->charge_to_client_hourly * 4, 2));
});
