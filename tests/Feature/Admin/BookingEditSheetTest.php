<?php

use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->client = Client::factory()->create();
});

describe('Edit booking sheet payload (#159)', function () {
    test('show json does not 500 when the client has been removed', function () {
        $group = BookingGroup::factory()->create([
            'client_id' => $this->client->id,
            'service_type' => ServiceType::Babysitter->value,
        ]);
        $booking = Booking::factory()->create(['booking_group_id' => $group->id]);

        // Soft-delete the client; the HasOneThrough now resolves to null.
        $this->client->delete();

        $this->actingAs($this->admin)
            ->getJson(route('bookings.show', $booking))
            ->assertOk();
    });

    test('show json normalizes string children to an empty array', function () {
        $group = BookingGroup::factory()->create([
            'client_id' => $this->client->id,
            'service_type' => ServiceType::Babysitter->value,
        ]);
        $booking = Booking::factory()->create(['booking_group_id' => $group->id]);

        // Simulate a legacy import that stored children as a JSON string.
        DB::table('booking_groups')->where('id', $group->id)->update(['children' => '"None"']);

        $this->actingAs($this->admin)
            ->getJson(route('bookings.show', $booking))
            ->assertOk()
            ->assertJsonPath('booking_group.children', []);
    });

    test('show json filters null child entries', function () {
        $group = BookingGroup::factory()->create([
            'client_id' => $this->client->id,
            'service_type' => ServiceType::Babysitter->value,
            'children' => [
                ['name' => 'Alex', 'birth_year' => 2018, 'birth_month' => 3],
                null,
            ],
        ]);
        $booking = Booking::factory()->create(['booking_group_id' => $group->id]);

        $this->actingAs($this->admin)
            ->getJson(route('bookings.show', $booking))
            ->assertOk()
            ->assertJsonCount(1, 'booking_group.children');
    });
});
