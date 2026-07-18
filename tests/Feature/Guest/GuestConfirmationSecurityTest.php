<?php

use App\Models\Booking;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/**
 * Regression tests for the guest booking confirmation page. It exposes the
 * client's name and address and was previously public: bookings resolve by
 * numeric id as well as ULID, so an attacker could walk /book/confirmation/1,
 * /2, /3... and harvest PII for every booking in the system.
 */
describe('Guest confirmation security', function () {
    function confirmationBooking(): Booking
    {
        $user = User::factory()->create(['role' => 'client']);
        $client = Client::factory()->for($user)->create();

        return Booking::factory()->forClient($client)->create();
    }

    test('an unsigned confirmation URL is rejected', function () {
        $booking = confirmationBooking();

        $this->get("/book/confirmation/{$booking->ulid}")->assertForbidden();
    });

    test('numeric booking ids cannot be enumerated', function () {
        $booking = confirmationBooking();

        $this->get("/book/confirmation/{$booking->id}")->assertForbidden();
    });

    test('a tampered signature is rejected', function () {
        $booking = confirmationBooking();

        $this->get("/book/confirmation/{$booking->ulid}?signature=forged")->assertForbidden();
    });

    test('a signed confirmation URL renders the page', function () {
        $booking = confirmationBooking();

        $url = URL::signedRoute('guest.bookings.confirmation', ['booking' => $booking->ulid]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('guest/bookings/confirmation'));
    });
});
