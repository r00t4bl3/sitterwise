<?php

use App\Http\Requests\StoreBookingRequest;
use App\Models\User;
use App\Models\ZipCode;
use App\Rules\ServiceableZip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function validateAddressZip(?string $zip): Illuminate\Contracts\Validation\Validator
{
    return Validator::make(['address_zip' => $zip], ['address_zip' => [new ServiceableZip]]);
}

it('rejects an out-of-area zip with a helpful message', function () {
    ZipCode::factory()->create(['zip_code' => '92069']);

    $validator = validateAddressZip('90001');

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('address_zip'))
        ->toContain('outside our service area');
});

it('accepts a serviced zip', function () {
    ZipCode::factory()->create(['zip_code' => '92069']);

    expect(validateAddressZip('92069')->passes())->toBeTrue();
});

it('leaves blank zips to the required/nullable rules', function () {
    ZipCode::factory()->create(['zip_code' => '92069']);

    expect(validateAddressZip(null)->passes())->toBeTrue();
});

it('enforces the service-area rule on the client booking flow', function () {
    $client = User::factory()->create(['role' => 'client']);
    $request = StoreBookingRequest::create('/bookings', 'POST', []);
    $request->setUserResolver(fn () => $client);

    $hasRule = collect($request->rules()['address_zip'])
        ->contains(fn ($rule) => $rule instanceof ServiceableZip);

    expect($hasRule)->toBeTrue();
});

it('does NOT enforce the service-area rule on the admin booking flow', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $request = StoreBookingRequest::create('/bookings', 'POST', []);
    $request->setUserResolver(fn () => $admin);

    $hasRule = collect($request->rules()['address_zip'])
        ->contains(fn ($rule) => $rule instanceof ServiceableZip);

    expect($hasRule)->toBeFalse();
});

it('shares the serviceable zip list to the frontend', function () {
    ZipCode::factory()->create(['zip_code' => '92069']);
    $user = User::factory()->create(['role' => 'client']);

    $this->actingAs($user)->get('/settings/appearance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('serviceable_zips', fn ($zips) => collect($zips)->contains('92069'))
        );
});
