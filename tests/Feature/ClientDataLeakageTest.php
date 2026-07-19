<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\ClientPaymentMethod;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeStripeHttpClient;

uses(RefreshDatabase::class);

afterEach(function () {
    FakeStripeHttpClient::reset();
});

/**
 * Regression tests: internal payout/margin figures, staff notes, and other
 * parties' PII must never reach a client-role user in an Inertia payload.
 */
beforeEach(function () {
    $this->seed([
        SpecialtyTypeSeeder::class,
        CertificationTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);

    $this->clientUser = User::factory()->create(['role' => 'client']);
    $this->client = Client::factory()->create(['user_id' => $this->clientUser->id, 'notes' => 'INTERNAL client note']);

    $this->caregiver = Caregiver::factory()->create([
        'notes' => 'INTERNAL caregiver note',
        'admin_rating' => 4,
        'stripe_account_id' => 'acct_secret',
    ]);
});

/** Recursively collect every scalar value in an Inertia prop tree. */
function flattenValues($data): array
{
    $out = [];
    array_walk_recursive((array) $data, function ($v) use (&$out) {
        $out[] = is_scalar($v) ? (string) $v : $v;
    });

    return $out;
}

describe('Client dashboard payload', function () {
    test('does not leak caregiver payout, staff notes, or caregiver internals', function () {
        Booking::factory()->forClient($this->client)->create([
            'caregiver_id' => $this->caregiver->id,
            'status' => BookingStatus::Confirmed->value,
            'start_datetime' => now()->addDay(),
            'end_datetime' => now()->addDay()->addHours(4),
        ]);

        $response = $this->actingAs($this->clientUser)->get('/dashboard');
        $response->assertOk();

        $client = $response->viewData('page')['props']['client'];
        $json = json_encode($client);

        // Caregiver name is expected; the caregiver's secrets are not.
        expect($json)->not->toContain('INTERNAL caregiver note');
        expect($json)->not->toContain('acct_secret');
        expect($json)->not->toContain('admin_rating');
        expect($json)->not->toContain('calendar_feed_token');
        expect($json)->not->toContain('status_token');
        // Payout / margin fields.
        expect($json)->not->toContain('paid_to_caregiver');
        expect($json)->not->toContain('sitterwise_cut');
        expect($json)->not->toContain('admin_notes');
        expect($json)->not->toContain('notes_to_sitterwise');

        // The legitimate shape still renders.
        $response->assertInertia(fn ($page) => $page
            ->has('client.nextBooking.caregiver.first_name')
            ->has('client.upcomingBookings')
        );
    });
});

describe('Client booking detail payload', function () {
    test('does not include paid_to_caregiver or sitterwise_cut', function () {
        FakeStripeHttpClient::install();

        $booking = Booking::factory()->forClient($this->client)->create([
            'caregiver_id' => $this->caregiver->id,
            'status' => BookingStatus::Completed->value,
        ]);

        $response = $this->actingAs($this->clientUser)->get(route('bookings.show', $booking));
        $response->assertOk();

        $json = json_encode($response->viewData('page')['props']['booking'] ?? []);
        expect($json)->not->toContain('paid_to_caregiver');
        expect($json)->not->toContain('sitterwise_cut');
    });
});

describe('Client payments payload', function () {
    test('does not serialize the full booking model', function () {
        FakeStripeHttpClient::install();

        $booking = Booking::factory()->forClient($this->client)->create([
            'caregiver_id' => $this->caregiver->id,
            'status' => BookingStatus::Completed->value,
        ]);

        $method = ClientPaymentMethod::factory()->create(['client_id' => $this->client->id]);
        ClientPayment::create([
            'booking_id' => $booking->id,
            'client_id' => $this->client->id,
            'payment_method_id' => $method->id,
            'amount' => 100,
            'currency' => 'usd',
            'status' => 'succeeded',
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_secret',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->clientUser)->get(route('payments.index'));
        $response->assertOk();

        $json = json_encode($response->viewData('page')['props']['payments']);
        expect($json)->not->toContain('paid_to_caregiver');
        expect($json)->not->toContain('sitterwise_cut');
        expect($json)->not->toContain('provider_payment_id');
        expect($json)->toContain('"id":'.$booking->id);
    });
});

describe('Transactions ledger authorization', function () {
    test('a client cannot access the transactions ledger', function () {
        $this->actingAs($this->clientUser)->get(route('transactions.index'))->assertForbidden();
    });

    test('a caregiver cannot access the transactions ledger', function () {
        $caregiverUser = User::factory()->create(['role' => 'caregiver']);

        $this->actingAs($caregiverUser)->get(route('transactions.index'))->assertForbidden();
    });

    test('an admin can still access the transactions ledger', function () {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('transactions.index'))->assertOk();
    });
});
