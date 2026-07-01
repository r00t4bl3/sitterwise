<?php

use App\Models\Client;
use App\Models\ClientPaymentMethod;
use App\Services\ClientPayment\ClientPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

function buildMockPaymentMethod(string $id, string $brand, string $last4, int $expMonth, int $expYear): object
{
    return (object) [
        'id' => $id,
        'card' => (object) [
            'brand' => $brand,
            'last4' => $last4,
            'exp_month' => $expMonth,
            'exp_year' => $expYear,
        ],
    ];
}

function createServiceWithMockStripe(object $stripeMethods): ClientPaymentService
{
    $customersMock = Mockery::mock();
    $customersMock->shouldReceive('allPaymentMethods')
        ->withAnyArgs()
        ->andReturn($stripeMethods->methods ?? []);
    $customersMock->shouldReceive('update')
        ->withAnyArgs()
        ->andReturnNull();

    $stripeMock = Mockery::mock(StripeClient::class);
    $stripeMock->customers = $customersMock;

    $service = Mockery::mock(ClientPaymentService::class)->makePartial();

    $ref = new ReflectionProperty(ClientPaymentService::class, 'stripe');
    $ref->setAccessible(true);
    $ref->setValue($service, $stripeMock);

    return $service;
}

test('syncPaymentMethodsFromStripe creates records from Stripe methods', function () {
    $client = Client::factory()->create([
        'stripe_customer_id' => 'cus_test123',
    ]);

    $stripeMethods = (object) [
        'methods' => [
            buildMockPaymentMethod('pm_111', 'visa', '4242', 12, 2027),
            buildMockPaymentMethod('pm_222', 'mastercard', '1111', 6, 2026),
        ],
    ];

    $service = createServiceWithMockStripe($stripeMethods);
    $result = $service->syncPaymentMethodsFromStripe($client);

    expect($result)->toHaveCount(2);
    expect($result[0]['brand'])->toBe('visa');
    expect($result[0]['last4'])->toBe('4242');
    expect($result[1]['brand'])->toBe('mastercard');

    expect(ClientPaymentMethod::count())->toBe(2);
    expect(ClientPaymentMethod::where('provider_method_id', 'pm_111')->exists())->toBeTrue();
    expect(ClientPaymentMethod::where('provider_method_id', 'pm_222')->exists())->toBeTrue();
});

test('syncPaymentMethodsFromStripe updates existing records', function () {
    $client = Client::factory()->create([
        'stripe_customer_id' => 'cus_test456',
    ]);

    ClientPaymentMethod::create([
        'client_id' => $client->id,
        'provider' => 'stripe',
        'provider_method_id' => 'pm_333',
        'brand' => 'amex',
        'last4' => '0000',
        'exp_month' => 1,
        'exp_year' => 2025,
        'status' => 'inactive',
    ]);

    $stripeMethods = (object) [
        'methods' => [
            buildMockPaymentMethod('pm_333', 'visa', '4242', 12, 2028),
        ],
    ];

    $service = createServiceWithMockStripe($stripeMethods);
    $service->syncPaymentMethodsFromStripe($client);

    $method = ClientPaymentMethod::where('provider_method_id', 'pm_333')->first();
    expect($method->brand)->toBe('visa');
    expect($method->last4)->toBe('4242');
    expect($method->exp_year)->toBe(2028);
    expect($method->status)->toBe('active');
});

test('syncPaymentMethodsFromStripe returns empty array when no stripe_customer_id', function () {
    $client = Client::factory()->create([
        'stripe_customer_id' => null,
    ]);

    $service = new ClientPaymentService;
    $result = $service->syncPaymentMethodsFromStripe($client);

    expect($result)->toBe([]);
});

test('syncPaymentMethodsFromStripe sets default on first method when none exists', function () {
    $client = Client::factory()->create([
        'stripe_customer_id' => 'cus_default_test',
    ]);

    $customersMock = Mockery::mock();
    $customersMock->shouldReceive('allPaymentMethods')
        ->withAnyArgs()
        ->andReturn([
            buildMockPaymentMethod('pm_default_1', 'visa', '4242', 12, 2027),
        ]);
    $customersMock->shouldReceive('update')
        ->withArgs(function ($customerId, $params) {
            return $customerId === 'cus_default_test'
                && ($params['invoice_settings']['default_payment_method'] ?? null) === 'pm_default_1';
        })
        ->andReturnNull();

    $stripeMock = Mockery::mock(StripeClient::class);
    $stripeMock->customers = $customersMock;

    $service = Mockery::mock(ClientPaymentService::class)->makePartial();
    $ref = new ReflectionProperty(ClientPaymentService::class, 'stripe');
    $ref->setAccessible(true);
    $ref->setValue($service, $stripeMock);

    $result = $service->syncPaymentMethodsFromStripe($client);

    expect($result[0]['is_default'])->toBeTrue();
    expect(ClientPaymentMethod::where('is_default', true)->count())->toBe(1);
});

test('syncPaymentMethodsFromStripe does not override existing default', function () {
    $client = Client::factory()->create([
        'stripe_customer_id' => 'cus_default_exists',
    ]);

    ClientPaymentMethod::create([
        'client_id' => $client->id,
        'provider' => 'stripe',
        'provider_method_id' => 'pm_existing_default',
        'brand' => 'visa',
        'last4' => '9999',
        'exp_month' => 12,
        'exp_year' => 2027,
        'status' => 'active',
        'is_default' => true,
    ]);

    $customersMock = Mockery::mock();
    $customersMock->shouldReceive('allPaymentMethods')
        ->withAnyArgs()
        ->andReturn([
            buildMockPaymentMethod('pm_new_1', 'mastercard', '1111', 6, 2026),
        ]);
    $customersMock->shouldReceive('update')->never();

    $stripeMock = Mockery::mock(StripeClient::class);
    $stripeMock->customers = $customersMock;

    $service = Mockery::mock(ClientPaymentService::class)->makePartial();
    $ref = new ReflectionProperty(ClientPaymentService::class, 'stripe');
    $ref->setAccessible(true);
    $ref->setValue($service, $stripeMock);

    $result = $service->syncPaymentMethodsFromStripe($client);

    expect($result[0]['is_default'])->toBeFalse();
    expect(ClientPaymentMethod::where('is_default', true)->count())->toBe(1);
    expect(ClientPaymentMethod::where('is_default', true)->first()->provider_method_id)->toBe('pm_existing_default');
});

test('showPaymentMethods triggers auto-sync when local methods are empty', function () {
    $client = Client::factory()->create([
        'stripe_customer_id' => 'cus_autosync',
    ]);

    $customersMock = Mockery::mock();
    $customersMock->shouldReceive('allPaymentMethods')
        ->withArgs(['cus_autosync', ['type' => 'card']])
        ->andReturn([
            buildMockPaymentMethod('pm_autosync_1', 'visa', '4242', 12, 2027),
        ]);
    $customersMock->shouldReceive('update')
        ->withAnyArgs()
        ->andReturnNull();

    $stripeMock = Mockery::mock(StripeClient::class);
    $stripeMock->customers = $customersMock;

    $service = Mockery::mock(ClientPaymentService::class)->makePartial();
    $ref = new ReflectionProperty(ClientPaymentService::class, 'stripe');
    $ref->setAccessible(true);
    $ref->setValue($service, $stripeMock);

    $this->actingAs($client->user);

    $result = $service->showPaymentMethods();

    expect($result)->toHaveCount(1);
    expect($result[0]['brand'])->toBe('visa');
    expect($result[0]['last4'])->toBe('4242');
});

test('showPaymentMethods returns local methods without syncing when they exist', function () {
    $client = Client::factory()->create([
        'stripe_customer_id' => 'cus_nosync',
    ]);

    ClientPaymentMethod::create([
        'client_id' => $client->id,
        'provider' => 'stripe',
        'provider_method_id' => 'pm_local',
        'brand' => 'discover',
        'last4' => '3333',
        'exp_month' => 3,
        'exp_year' => 2026,
        'status' => 'active',
        'is_default' => true,
    ]);

    $service = Mockery::mock(ClientPaymentService::class)->makePartial();
    $ref = new ReflectionProperty(ClientPaymentService::class, 'stripe');
    $ref->setAccessible(true);
    $ref->setValue($service, Mockery::mock(StripeClient::class));

    $this->actingAs($client->user);

    $result = $service->showPaymentMethods();

    expect($result)->toHaveCount(1);
    expect($result[0]['brand'])->toBe('discover');
    expect($result[0]['last4'])->toBe('3333');
});

test('artisan command syncs all clients with stripe_customer_id', function () {
    $client1 = Client::factory()->create([
        'first_name' => 'Alice',
        'stripe_customer_id' => 'cus_a',
    ]);
    $client2 = Client::factory()->create([
        'first_name' => 'Bob',
        'stripe_customer_id' => 'cus_b',
    ]);
    Client::factory()->create([
        'stripe_customer_id' => null,
    ]);

    $customersMock = Mockery::mock();
    $customersMock->shouldReceive('allPaymentMethods')
        ->withAnyArgs()
        ->andReturn([]);
    $customersMock->shouldReceive('update')
        ->withAnyArgs()
        ->andReturnNull();

    $stripeMock = Mockery::mock(StripeClient::class);
    $stripeMock->customers = $customersMock;

    $service = Mockery::mock(ClientPaymentService::class)->makePartial();
    $ref = new ReflectionProperty(ClientPaymentService::class, 'stripe');
    $ref->setAccessible(true);
    $ref->setValue($service, $stripeMock);

    app()->instance(ClientPaymentService::class, $service);

    $this->artisan('payments:sync-client-methods')
        ->assertSuccessful();
});

test('artisan command syncs single client with --client option', function () {
    $client = Client::factory()->create([
        'first_name' => 'Charlie',
        'last_name' => 'Test',
        'stripe_customer_id' => 'cus_charlie',
    ]);

    $customersMock = Mockery::mock();
    $customersMock->shouldReceive('allPaymentMethods')
        ->withAnyArgs()
        ->andReturn([
            buildMockPaymentMethod('pm_charlie_1', 'visa', '4242', 12, 2027),
        ]);
    $customersMock->shouldReceive('update')
        ->withAnyArgs()
        ->andReturnNull();

    $stripeMock = Mockery::mock(StripeClient::class);
    $stripeMock->customers = $customersMock;

    $service = Mockery::mock(ClientPaymentService::class)->makePartial();
    $ref = new ReflectionProperty(ClientPaymentService::class, 'stripe');
    $ref->setAccessible(true);
    $ref->setValue($service, $stripeMock);

    app()->instance(ClientPaymentService::class, $service);

    $this->artisan('payments:sync-client-methods', ['--client' => $client->id])
        ->expectsOutputToContain('Charlie Test')
        ->assertSuccessful();
});

test('artisan command shows error for non-existent client', function () {
    $this->artisan('payments:sync-client-methods', ['--client' => 99999])
        ->assertExitCode(1);
});

test('artisan command warns when client has no stripe_customer_id', function () {
    $client = Client::factory()->create([
        'stripe_customer_id' => null,
    ]);

    $this->artisan('payments:sync-client-methods', ['--client' => $client->id])
        ->assertSuccessful();
});
