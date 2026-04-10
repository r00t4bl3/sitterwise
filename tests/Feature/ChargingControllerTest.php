<?php

use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\CaregiverPayoutMethod;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

describe('ChargingController', function () {
    test('admin can access charge page', function () {
        $booking = Booking::factory()->create([
            'client_id' => $this->client->id,
            'caregiver_id' => $this->caregiver->id,
            'payment_status' => 'pending',
            'total_amount' => 100,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.bookings.charge.create').'?booking_id='.$booking->id);

        $response->assertSuccessful();
    });

    test('charge page shows caregiver payout summary', function () {
        $booking = Booking::factory()->create([
            'client_id' => $this->client->id,
            'caregiver_id' => $this->caregiver->id,
            'payment_status' => 'pending',
            'total_amount' => 100,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.bookings.charge.create').'?booking_id='.$booking->id);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('booking'));
    });

    test('returns error when booking already charged', function () {
        $booking = Booking::factory()->create([
            'client_id' => $this->client->id,
            'caregiver_id' => $this->caregiver->id,
            'payment_status' => 'captured',
            'total_amount' => 100,
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
        $booking = Booking::factory()->create([
            'client_id' => $this->client->id,
            'caregiver_id' => $this->caregiver->id,
            'payment_status' => 'pending',
            'requires_payment' => false,
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
        $booking = Booking::factory()->create([
            'client_id' => $this->client->id,
            'caregiver_id' => null,
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
            'message' => 'This booking has no assigned caregiver.',
        ]);
    });

    test('calculates caregiver payout correctly', function () {
        $booking = Booking::factory()->create([
            'client_id' => $this->client->id,
            'caregiver_id' => $this->caregiver->id,
            'payment_status' => 'pending',
            'total_amount' => 100,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.bookings.calculateTotal', $booking));

        $response->assertOk();
        $response->assertJson([
            'base_amount' => 100,
            'caregiver_gross' => 100,
            'platform_fee' => 12,
            'caregiver_net' => 88,
        ]);
    });
});
