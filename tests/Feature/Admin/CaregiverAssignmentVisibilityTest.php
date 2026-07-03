<?php

use App\Enums\CaregiverStatus;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ClientChild;
use App\Models\Hotel;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->seed(AttributeDefinitionSeeder::class);
    $this->seed(CertificationTypeSeeder::class);
    $this->seed(LocationSeeder::class);
    $this->seed(SpecialtyTypeSeeder::class);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->client = Client::factory()->create();
    $this->hotel = Hotel::factory()->create();
});

/*
 * Regression coverage for the caregiver-assignment visibility bug (#278/#279).
 *
 * The caregiver "My Jobs" list (JobController::index) and the caregiver
 * dashboard only show bookings whose status is Confirmed/Completed/Paid. So any
 * admin flow that assigns a caregiver must also move the booking to Confirmed,
 * otherwise the admin sees the caregiver assigned while the caregiver sees
 * nothing.
 */
describe('caregiver assignment makes a job visible to the caregiver', function () {
    test('replace-caregiver on an unassigned received booking confirms it and shows it in My Jobs', function () {
        $caregiver = Caregiver::factory()->create(['status' => CaregiverStatus::Active->value]);

        $booking = Booking::factory()
            ->forClient($this->client)
            ->withBookingGroup(fn ($group) => $group->state(['service_type' => 'babysitter']))
            ->create([
                'caregiver_id' => null,
                'status' => 'received',
                'start_datetime' => now()->addDays(5),
                'end_datetime' => now()->addDays(5)->addHours(4),
            ]);

        $this->actingAs($this->admin)
            ->post(route('bookings.replace-caregiver', $booking), [
                'caregiver_id' => $caregiver->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $booking->refresh();
        expect($booking->caregiver_id)->toBe($caregiver->id);
        expect($booking->status)->toBe('confirmed');

        // The caregiver can now actually see the job in their list.
        $this->actingAs($caregiver->user)
            ->get(route('jobs.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('caregiver/jobs/index')
                ->has('jobs.data', 1)
                ->where('jobs.data.0.id', $booking->id)
            );
    });

    test('creating a booking with a caregiver and pending status auto-confirms it', function () {
        $caregiver = Caregiver::factory()->create(['status' => CaregiverStatus::Active->value]);

        $this->actingAs($this->admin)
            ->post(route('bookings.store'), [
                'client_id' => $this->client->id,
                'service_type' => 'babysitter',
                'location_type' => 'hotel',
                'start_datetime' => now()->addDays(3)->toISOString(),
                'end_datetime' => now()->addDays(3)->addHours(4)->toISOString(),
                'hotel_id' => $this->hotel->id,
                'caregiver_id' => $caregiver->id,
                'total_amount' => 100,
                'status' => 'pending',
                'payment_status' => 'pending',
                'address_line1' => '123 Test St',
                'address_city' => 'San Diego',
                'address_state' => 'CA',
                'address_zip' => '92101',
                'new_children' => [
                    ['name' => 'Kiddo', 'gender' => 'female', 'birth_month' => 5, 'birth_year' => 2018],
                ],
            ])
            ->assertSessionHasNoErrors();

        $booking = Booking::where('caregiver_id', $caregiver->id)->latest('id')->first();
        expect($booking)->not->toBeNull();
        expect($booking->status)->toBe('confirmed');
    });
});

describe('assignment promotion never overrides a finalized status', function () {
    test('admin assigning a caregiver while explicitly marking completed keeps completed', function () {
        $caregiver = Caregiver::factory()->create(['status' => CaregiverStatus::Active->value]);

        $booking = Booking::factory()
            ->forClient($this->client)
            ->withBookingGroup(fn ($group) => $group->state(['service_type' => 'babysitter']))
            ->create([
                'caregiver_id' => null,
                'status' => 'received',
                'start_datetime' => now()->subDays(1),
                'end_datetime' => now()->subDays(1)->addHours(4),
            ]);

        $child = ClientChild::factory()->create(['client_id' => $this->client->id]);

        $this->actingAs($this->admin)
            ->patch(route('bookings.update', $booking), [
                'client_id' => $booking->client_id,
                'service_type' => $booking->service_type,
                'location_type' => $booking->location_type,
                'start_datetime' => $booking->start_datetime->toISOString(),
                'end_datetime' => $booking->end_datetime->toISOString(),
                'caregiver_id' => $caregiver->id,
                'status' => 'completed',
                'payment_status' => 'pending',
                'new_children' => [
                    ['name' => $child->name, 'gender' => $child->gender, 'birth_month' => $child->birth_month, 'birth_year' => $child->birth_year],
                ],
            ])
            ->assertSessionHasNoErrors();

        $booking->refresh();
        expect($booking->caregiver_id)->toBe($caregiver->id);
        expect($booking->status)->toBe('completed');
    });
});
