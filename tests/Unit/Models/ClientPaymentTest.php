<?php

use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\ClientPaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can be instantiated', function () {
    $payment = ClientPayment::factory()->make();

    expect($payment)->toBeInstanceOf(ClientPayment::class);
});

test('has correct fillable fields', function () {
    $payment = ClientPayment::factory()->create([
        'amount' => 150.00,
        'currency' => 'usd',
        'status' => 'pending',
        'provider' => 'stripe',
    ]);

    expect((float) $payment->amount)->toBe(150.00);
    expect($payment->currency)->toBe('usd');
    expect($payment->status)->toBe('pending');
    expect($payment->provider)->toBe('stripe');
});

test('casts attributes correctly', function () {
    $now = now();
    $payment = ClientPayment::factory()->create([
        'amount' => 99.99,
        'paid_at' => $now,
        'metadata' => ['type' => 'booking', 'booking_id' => 123],
    ]);

    expect((float) $payment->amount)->toBe(99.99);
    expect($payment->paid_at)->toBeInstanceOf(CarbonImmutable::class);
    expect($payment->paid_at->timestamp)->toBe($now->timestamp);
    expect($payment->metadata)->toBeArray();
    expect($payment->metadata['type'])->toBe('booking');
});

test('defines client relationship', function () {
    $client = Client::factory()->create();
    $payment = ClientPayment::factory()->create(['client_id' => $client->id]);

    expect($payment->client)->toBeInstanceOf(Client::class);
    expect($payment->client->id)->toBe($client->id);
});

test('defines booking relationship', function () {
    $booking = Booking::factory()->create();
    $payment = ClientPayment::factory()->create(['booking_id' => $booking->id]);

    expect($payment->booking)->toBeInstanceOf(Booking::class);
    expect($payment->booking->id)->toBe($booking->id);
});

test('defines paymentMethod relationship', function () {
    $paymentMethod = ClientPaymentMethod::factory()->create();
    $payment = ClientPayment::factory()->create(['payment_method_id' => $paymentMethod->id]);

    expect($payment->paymentMethod)->toBeInstanceOf(ClientPaymentMethod::class);
    expect($payment->paymentMethod->id)->toBe($paymentMethod->id);
});

test('factory states produce correct statuses', function () {
    $captured = ClientPayment::factory()->captured()->create();
    expect($captured->status)->toBe('captured');
    expect($captured->paid_at)->not->toBeNull();

    $failed = ClientPayment::factory()->failed()->create();
    expect($failed->status)->toBe('failed');
    expect($failed->paid_at)->toBeNull();

    $refunded = ClientPayment::factory()->refunded()->create();
    expect($refunded->status)->toBe('refunded');
});
