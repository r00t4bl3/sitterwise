<?php

use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientChild;
use App\Models\ClientPet;
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
    $this->seed(AttributeDefinitionSeeder::class);
    $this->seed(CertificationTypeSeeder::class);
    $this->seed(LocationSeeder::class);
    $this->seed(SpecialtyTypeSeeder::class);
    $this->seed(PricingRulesTableSeeder::class);
    $this->admin = User::factory()->create(['role' => 'admin']);
});

/**
 * A minimal valid admin update payload for the given booking.
 *
 * @return array<string, mixed>
 */
function clientSwitchPayload(Booking $booking, array $overrides = []): array
{
    $start = $booking->start_datetime;

    return array_merge([
        'client_id' => $booking->client_id,
        'service_type' => 'babysitter',
        'location_type' => 'private_home',
        'start_datetime' => $start->toISOString(),
        'end_datetime' => $start->copy()->addHours(4)->toISOString(),
        'status' => 'received',
        'payment_status' => $booking->payment_status ?? 'unpaid',
        'address_line1' => '123 Test St',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'new_children' => [
            ['name' => 'Test Kid', 'gender' => '', 'birth_month' => '', 'birth_year' => ''],
        ],
        'save_children_pets_to_profile' => false,
    ], $overrides);
}

describe('Booking client switch (Kirwan/Sarma regression)', function () {
    test('switching client never syncs the form family data to the old client profile', function () {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();

        $luka = ClientChild::create([
            'client_id' => $clientA->id,
            'name' => 'Luka',
            'gender' => 'male',
            'birth_date' => '2025-10-01',
        ]);
        ClientChild::create([
            'client_id' => $clientB->id,
            'name' => 'Ishaan',
            'gender' => null,
            'birth_date' => null,
        ]);

        $booking = Booking::factory()->forClient($clientA)->create();

        // Simulate the edit sheet after picking client B: the form's children
        // were auto-filled from B's profile, and the sync checkbox is on.
        $response = $this->actingAs($this->admin)->patch(
            route('bookings.update', $booking),
            clientSwitchPayload($booking, [
                'client_id' => $clientB->id,
                'save_children_pets_to_profile' => true,
                'new_children' => [
                    ['name' => 'Ishaan', 'gender' => '', 'birth_month' => '', 'birth_year' => ''],
                ],
            ]),
        );

        $response->assertRedirect();

        // Old client keeps their own family data - nothing crossed over.
        expect(ClientChild::where('client_id', $clientA->id)->pluck('name')->all())
            ->toBe(['Luka'])
            ->and(ClientChild::withTrashed()->find($luka->id)->trashed())->toBeFalse()
            ->and(ClientChild::where('client_id', $clientB->id)->count())->toBe(1);

        // The group moved to the new client WITH matching denormalized fields.
        $group = $booking->fresh()->bookingGroup;
        expect($group->client_id)->toBe($clientB->id)
            ->and($group->client_first_name)->toBe($clientB->first_name)
            ->and($group->client_email)->toBe($clientB->user->email);

        // The old family's address is not saved into either address book.
        expect(ClientAddress::whereIn('client_id', [$clientA->id, $clientB->id])->count())->toBe(0);
    });

    test('same-client sync preserves existing birth data and gender when the form omits them', function () {
        $client = Client::factory()->create();
        $luka = ClientChild::create([
            'client_id' => $client->id,
            'name' => 'Luka',
            'gender' => 'male',
            'birth_date' => '2025-10-01',
        ]);

        $booking = Booking::factory()->forClient($client)->create();

        $response = $this->actingAs($this->admin)->patch(
            route('bookings.update', $booking),
            clientSwitchPayload($booking, [
                'save_children_pets_to_profile' => true,
                'new_children' => [
                    ['name' => 'Luka', 'gender' => '', 'birth_month' => '', 'birth_year' => ''],
                ],
            ]),
        );

        $response->assertRedirect();

        $children = ClientChild::where('client_id', $client->id)->get();
        expect($children)->toHaveCount(1)
            ->and($children->first()->id)->toBe($luka->id)
            ->and($children->first()->gender)->toBe('male')
            ->and($children->first()->birth_year)->toBe(2025)
            ->and($children->first()->birth_month)->toBe(10);
    });

    test('same-client sync writes new children birth data through to the profile', function () {
        $client = Client::factory()->create();
        $booking = Booking::factory()->forClient($client)->create();

        $response = $this->actingAs($this->admin)->patch(
            route('bookings.update', $booking),
            clientSwitchPayload($booking, [
                'save_children_pets_to_profile' => true,
                'new_children' => [
                    ['name' => 'Nina', 'gender' => 'female', 'birth_month' => '10', 'birth_year' => '2025'],
                ],
            ]),
        );

        $response->assertRedirect();

        $nina = ClientChild::where('client_id', $client->id)->where('name', 'Nina')->first();
        expect($nina)->not->toBeNull()
            ->and($nina->gender)->toBe('female')
            ->and($nina->birth_year)->toBe(2025)
            ->and($nina->birth_month)->toBe(10);
    });

    test('client switch does not cross pets between profiles either', function () {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();

        ClientPet::create(['client_id' => $clientA->id, 'name' => 'Rex', 'type' => 'dog']);
        ClientPet::create(['client_id' => $clientB->id, 'name' => 'Milo', 'type' => 'cat']);

        $booking = Booking::factory()->forClient($clientA)->create();

        $response = $this->actingAs($this->admin)->patch(
            route('bookings.update', $booking),
            clientSwitchPayload($booking, [
                'client_id' => $clientB->id,
                'save_children_pets_to_profile' => true,
                'new_pets' => [
                    ['name' => 'Milo', 'type' => 'cat', 'breed' => '', 'notes' => ''],
                ],
            ]),
        );

        $response->assertRedirect();

        expect(ClientPet::where('client_id', $clientA->id)->pluck('name')->all())->toBe(['Rex'])
            ->and(ClientPet::where('client_id', $clientB->id)->count())->toBe(1);
    });

    test('same-client sync still removes children deleted from the booking form', function () {
        $client = Client::factory()->create();
        ClientChild::create(['client_id' => $client->id, 'name' => 'Luka', 'gender' => 'male', 'birth_date' => '2025-10-01']);
        ClientChild::create(['client_id' => $client->id, 'name' => 'Nina', 'gender' => 'female', 'birth_date' => '2023-06-01']);

        $booking = Booking::factory()->forClient($client)->create();

        // Nina was removed from the form; only Luka remains in the snapshot.
        $response = $this->actingAs($this->admin)->patch(
            route('bookings.update', $booking),
            clientSwitchPayload($booking, [
                'save_children_pets_to_profile' => true,
                'new_children' => [
                    ['name' => 'Luka', 'gender' => 'male', 'birth_month' => '10', 'birth_year' => '2025'],
                ],
            ]),
        );

        $response->assertRedirect();

        expect(ClientChild::where('client_id', $client->id)->pluck('name')->all())->toBe(['Luka']);
    });

    test('repeated saves do not duplicate the client address', function () {
        $client = Client::factory()->create();
        $booking = Booking::factory()->forClient($client)->create();

        foreach (range(1, 2) as $i) {
            $this->actingAs($this->admin)->patch(
                route('bookings.update', $booking),
                clientSwitchPayload($booking, ['address_id' => null]),
            )->assertRedirect();
        }

        expect(
            ClientAddress::where('client_id', $client->id)
                ->where('line1', '123 Test St')
                ->count(),
        )->toBe(1);
    });

    describe('Update path child birthday handling', function () {
        test('year-only new child through update defaults birth_month to current month', function () {
            $client = Client::factory()->create();
            $booking = Booking::factory()->forClient($client)->create();
            $currentMonth = now()->month;

            $this->actingAs($this->admin)->patch(
                route('bookings.update', $booking),
                clientSwitchPayload($booking, [
                    'save_children_pets_to_profile' => true,
                    'new_children' => [
                        ['name' => 'YearOnly New', 'gender' => 'male', 'birth_month' => '', 'birth_year' => '2020'],
                    ],
                ]),
            )->assertRedirect();

            $saved = ClientChild::where('client_id', $client->id)
                ->where('name', 'YearOnly New')
                ->first();

            expect($saved)->not->toBeNull();
            expect($saved->birth_year)->toBe(2020);
            expect($saved->birth_month)->toBe($currentMonth);
        });

        test('month-only new child through update sets birth_date to null', function () {
            $client = Client::factory()->create();
            $booking = Booking::factory()->forClient($client)->create();

            $this->actingAs($this->admin)->patch(
                route('bookings.update', $booking),
                clientSwitchPayload($booking, [
                    'save_children_pets_to_profile' => true,
                    'new_children' => [
                        ['name' => 'MonthOnly New', 'gender' => 'female', 'birth_month' => '6', 'birth_year' => ''],
                    ],
                ]),
            )->assertRedirect();

            $saved = ClientChild::where('client_id', $client->id)
                ->where('name', 'MonthOnly New')
                ->first();

            expect($saved)->not->toBeNull();
            expect($saved->birth_date)->toBeNull();
        });
    });
});
