<?php

use App\Models\Client;
use App\Models\User;
use App\Services\ClientPayment\ClientPaymentServiceFactory;
use App\Services\ClientPayment\Contracts\ClientPaymentServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\PaymentMethod;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->client = Client::factory()->create();
});

test('admin can store a payment method for a client', function () {
    $mockService = mock(ClientPaymentServiceInterface::class);
    $mockFactory = mock(ClientPaymentServiceFactory::class);

    $mockFactory->shouldReceive('make')->andReturn($mockService);
    app()->instance(ClientPaymentServiceFactory::class, $mockFactory);

    $stripePaymentMethod = new PaymentMethod('pm_123');
    $stripePaymentMethod->card = (object) [
        'brand' => 'visa',
        'last4' => '4242',
        'exp_month' => 12,
        'exp_year' => 2030,
    ];

    $mockService->shouldReceive('retrievePaymentMethod')
        ->with('pm_123')
        ->andReturn($stripePaymentMethod);

    $mockService->shouldReceive('setClient')
        ->with(Mockery::type(Client::class))
        ->andReturnSelf();

    $mockService->shouldReceive('storePaymentMethod')
        ->with([
            'payment_method_id' => 'pm_123',
            'brand' => 'visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
        ])
        ->andReturn(['id' => 1]);

    actingAs($this->user)
        ->post(route('clients.paymentMethod.store', $this->client), [
            'payment_method_id' => 'pm_123',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Payment method added successfully');
});

test('non-admin cannot store a payment method for a client', function () {
    $user = User::factory()->create(['role' => 'caregiver']);

    actingAs($user)
        ->post(route('clients.paymentMethod.store', $this->client), [
            'payment_method_id' => 'pm_123',
        ])
        ->assertForbidden();
});
