<?php

use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\CaregiverPayoutMethod;
use App\Models\Client;
use App\Models\User;
use App\Services\Billing\JobBillingService;
use App\Services\CaregiverPayout\CaregiverPayoutService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->client = Client::factory()->create();
    $this->caregiver = Caregiver::factory()->create();
    $this->payoutMethod = CaregiverPayoutMethod::factory()->create([
        'caregiver_id' => $this->caregiver->id,
        'is_default' => true,
    ]);
});

function makeBooking(Client $client, ?Caregiver $caregiver = null, array $overrides = []): Booking
{
    return Booking::factory()->forClient($client)->create(array_merge([
        'caregiver_id' => $caregiver?->id,
        'payment_status' => 'pending',
    ], $overrides));
}

describe('ChargingController', function () {
    test('admin can access charge page', function () {
        $booking = makeBooking($this->client, $this->caregiver);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.bookings.charge.create').'?booking_id='.$booking->id);

        $response->assertSuccessful();
    });

    test('charge page shows caregiver payout summary', function () {
        $booking = makeBooking($this->client, $this->caregiver);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.bookings.charge.create').'?booking_id='.$booking->id);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('booking'));
    });

    test('returns error when booking already charged', function () {
        $booking = makeBooking($this->client, $this->caregiver, [
            'payment_status' => 'captured',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.bookings.charge', $booking), [
                'reimbursement' => 0,
                'tip' => 0,
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'This booking has already been charged.',
        ]);
    });

    test('returns error when booking does not require payment', function () {
        $booking = Booking::factory()->forClient($this->client)->comped()->create([
            'caregiver_id' => $this->caregiver->id,
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.bookings.charge', $booking), [
                'reimbursement' => 0,
                'tip' => 0,
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'This booking does not require payment.',
        ]);
    });

    test('returns error when booking has no caregiver', function () {
        $booking = makeBooking($this->client);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.bookings.charge', $booking), [
                'reimbursement' => 0,
                'tip' => 0,
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'This booking has no assigned caregiver.',
        ]);
    });

    test('calculate total returns booking model fields', function () {
        $booking = makeBooking($this->client, $this->caregiver);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.bookings.calculateTotal', $booking));

        $response->assertOk();
        $response->assertJsonStructure([
            'charge_to_client',
            'reimbursement',
            'bonus',
            'tip',
            'total_service_amount',
            'total_amount',
            'paid_to_caregiver',
            'sitterwise_cut',
            'paid_to_caregiver_total',
        ]);
    });

    test('charge succeeds without transfer when flag is disabled', function () {
        $booking = makeBooking($this->client, $this->caregiver);

        mock(JobBillingService::class)
            ->shouldReceive('charge')
            ->once()
            ->andReturn([
                'success' => true,
                'message' => 'Payment successful',
                'payment_intent_id' => 'pi_test',
                'amount' => 200.00,
            ]);

        mock(CaregiverPayoutService::class)
            ->shouldNotReceive('transferFunds');

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.bookings.charge', $booking), [
                'reimbursement' => 0,
                'tip' => 0,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'step' => 'complete',
        ]);
    });

    test('charge triggers transfer when flag is enabled', function () {
        config(['services.stripe.enable_caregiver_transfers' => true]);

        $booking = makeBooking($this->client, $this->caregiver);

        mock(JobBillingService::class)
            ->shouldReceive('charge')
            ->once()
            ->andReturn([
                'success' => true,
                'message' => 'Payment successful',
                'payment_intent_id' => 'pi_test',
                'amount' => 200.00,
            ]);

        mock(CaregiverPayoutService::class)
            ->shouldReceive('transferFunds')
            ->once()
            ->andReturn([
                'success' => true,
                'message' => 'Payout successful',
                'transfer_id' => 'tr_test',
                'payout_id' => 1,
            ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.bookings.charge', $booking), [
                'reimbursement' => 0,
                'tip' => 0,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'step' => 'complete',
        ]);
    });
});
