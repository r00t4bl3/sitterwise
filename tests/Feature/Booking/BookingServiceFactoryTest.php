<?php

use App\Models\User;
use App\Services\Booking\AdminBookingService;
use App\Services\Booking\BookingServiceFactory;
use App\Services\Booking\CaregiverBookingService;
use App\Services\Booking\ClientBookingService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it resolves the service that matches the authenticated user role', function (string $role, string $expected) {
    // The factory dispatches purely on role, so no related record is needed.
    $this->actingAs(User::factory()->create(['role' => $role]));

    expect(app(BookingServiceFactory::class)->make())->toBeInstanceOf($expected);
})->with([
    'admin' => ['admin', AdminBookingService::class],
    'client' => ['client', ClientBookingService::class],
    'caregiver' => ['caregiver', CaregiverBookingService::class],
    'other roles fall back to admin' => ['super_admin', AdminBookingService::class],
]);

test('it throws (rather than dd/defaulting to admin) when unauthenticated', function () {
    expect(fn () => app(BookingServiceFactory::class)->make())
        ->toThrow(AuthenticationException::class);
});
