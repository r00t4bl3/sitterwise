<?php

use App\Models\Client;
use App\Models\ClientPaymentMethod;
use App\Services\ClientPayment\ClientPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

/**
 * Builds a StripeClient test double whose nested service accessors
 * (`paymentMethods`, `customers`) resolve to no-op mocks, so the real
 * ClientPaymentService can run without touching the Stripe API.
 */
function fakeStripeClient(): StripeClient
{
    $paymentMethods = Mockery::mock();
    $paymentMethods->shouldReceive('attach')->andReturnNull();

    $customers = Mockery::mock();
    $customers->shouldReceive('update')->andReturnNull();

    // StripeClient's `__get` delegates to `getService()`; Mockery can't
    // intercept the magic method, so stub the delegate it calls instead.
    $stripe = Mockery::mock(StripeClient::class);
    $stripe->shouldReceive('getService')->with('paymentMethods')->andReturn($paymentMethods);
    $stripe->shouldReceive('getService')->with('customers')->andReturn($customers);

    return $stripe;
}

function paymentMethodData(array $overrides = []): array
{
    return array_merge([
        'payment_method_id' => 'pm_test_1234567890',
        'brand' => 'visa',
        'last4' => '2419',
        'exp_month' => 2,
        'exp_year' => 2029,
        'metadata' => [],
    ], $overrides);
}

beforeEach(function () {
    Notification::fake();

    $this->client = Client::factory()->create([
        'stripe_customer_id' => 'cus_test_123',
    ]);

    $this->service = (new ClientPaymentService(fakeStripeClient()))->setClient($this->client);
});

test('storing the same payment method twice creates only one row', function () {
    $this->service->storePaymentMethod(paymentMethodData());
    $this->service->storePaymentMethod(paymentMethodData());

    expect(ClientPaymentMethod::where('provider_method_id', 'pm_test_1234567890')->count())->toBe(1);
});

test('storing a payment method that the webhook already inserted does not crash and preserves default', function () {
    // Simulate the payment_method.attached webhook winning the race and
    // inserting the row (as the default) before the client returns.
    $existing = ClientPaymentMethod::create([
        'client_id' => $this->client->id,
        'provider' => 'stripe',
        'provider_method_id' => 'pm_test_1234567890',
        'brand' => 'visa',
        'last4' => '2419',
        'exp_month' => 2,
        'exp_year' => 2029,
        'status' => 'active',
        'is_default' => true,
        'metadata' => [],
    ]);

    $result = $this->service->storePaymentMethod(paymentMethodData());

    expect(ClientPaymentMethod::where('provider_method_id', 'pm_test_1234567890')->count())->toBe(1)
        ->and($result['id'])->toBe($existing->id)
        ->and($existing->fresh()->is_default)->toBeTrue();
});

test('the first stored payment method becomes the default', function () {
    $result = $this->service->storePaymentMethod(paymentMethodData());

    expect($result['is_default'])->toBeTrue();
});

test('a second distinct payment method does not become default when one already exists', function () {
    $this->service->storePaymentMethod(paymentMethodData(['payment_method_id' => 'pm_first']));
    $result = $this->service->storePaymentMethod(paymentMethodData(['payment_method_id' => 'pm_second']));

    expect($result['is_default'])->toBeFalse()
        ->and(ClientPaymentMethod::where('client_id', $this->client->id)->where('is_default', true)->count())->toBe(1);
});
