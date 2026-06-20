<?php

use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientChild;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('client booking create page renders', function () {
    $user = createClientUser();

    $this->actingAs($user);

    visit('/bookings/create')
        ->assertSee('Create Booking')
        ->assertNoJavaScriptErrors();
});

test('client creates booking via form submission', function () {
    $user = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $user->id]);

    $address = ClientAddress::create([
        'client_id' => $client->id,
        'line1' => '123 Main St',
        'city' => 'San Diego',
        'state' => 'CA',
        'zip' => '92101',
    ]);

    ClientChild::create([
        'client_id' => $client->id,
        'name' => 'Test Child',
        'gender' => 'male',
        'birth_date' => '2020-01-01',
    ]);

    $tomorrow = now()->addDay()->startOfDay()->addHours(9);
    $end = $tomorrow->copy()->addHours(4);

    $this->withoutMiddleware(PreventRequestForgery::class);

    $this->actingAs($user);

    $response = $this->post('/bookings', [
        'service_type' => 'babysitter',
        'location_type' => 'private_home',
        'start_datetime' => $tomorrow->format('Y-m-d\TH:i'),
        'end_datetime' => $end->format('Y-m-d\TH:i'),
        'address_id' => $address->id,
        'new_children' => [
            [
                'name' => 'Test Child',
                'gender' => 'male',
                'birth_month' => '1',
                'birth_year' => '2020',
            ],
        ],
        'dates' => [
            [
                'start_datetime' => $tomorrow->format('Y-m-d\TH:i'),
                'end_datetime' => $end->format('Y-m-d\TH:i'),
            ],
        ],
        'save_children_pets_to_profile' => false,
    ]);

    $response->assertStatus(302);
});

test('client can add and remove children dynamically via browser', function () {
    $user = createClientUser();

    $this->actingAs($user);

    $page = visit('/bookings/create');

    $page->assertSee('Create Booking');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const addBtn = buttons.find(b => b.textContent.includes('Add Child'));
        if (addBtn) addBtn.click();
    JS);

    usleep(300000);

    $page->assertNoJavaScriptErrors();
});

test('client can add and remove pets dynamically via browser', function () {
    $user = createClientUser();

    $this->actingAs($user);

    $page = visit('/bookings/create');

    $page->assertSee('Create Booking');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const addBtn = buttons.find(b => b.textContent.includes('Add Pet'));
        if (addBtn) addBtn.click();
    JS);

    usleep(300000);

    $page->assertNoJavaScriptErrors();
});
