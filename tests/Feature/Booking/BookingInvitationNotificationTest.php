<?php

use App\Enums\ServiceType;
use App\Events\BookingInvitationSent;
use App\Listeners\SendBookingInvitationNotifications;
use App\Mail\CaregiverBookingInvitationMail;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\PricingRule;
use App\Notifications\BookingInvitationNotification;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        AttributeDefinitionSeeder::class,
        CertificationTypeSeeder::class,
        LocationSeeder::class,
        SpecialtyTypeSeeder::class,
    ]);
});

function invitedBooking(): array
{
    $client = Client::factory()->create();
    $caregiver = Caregiver::factory()->create();

    PricingRule::create([
        'service_type' => ServiceType::Babysitter->value,
        'number_of_children' => 0,
        'is_for_pets' => false,
        'charge_to_client' => 20,
        'paid_to_caregiver' => 15,
        'sitterwise_cut' => 5,
        'payment_form' => 'Stripe',
    ]);

    $booking = Booking::factory()->forClient($client)->create();

    return [$booking, $caregiver, $client];
}

describe('Booking Invitation Notifications', function () {
    test('BookingInvitationSent event dispatches', function () {
        Event::fake();

        [$booking, $caregiver] = invitedBooking();

        event(new BookingInvitationSent($booking, $caregiver));

        Event::assertDispatched(BookingInvitationSent::class);
    });

    test('SendBookingInvitationNotifications listener is queued', function () {
        $listener = app(SendBookingInvitationNotifications::class);

        expect($listener)->toBeInstanceOf(ShouldQueue::class);
    });

    test('sends notification to caregiver user', function () {
        Notification::fake();

        [$booking, $caregiver] = invitedBooking();

        event(new BookingInvitationSent($booking, $caregiver));

        Notification::assertSentTo(
            $caregiver->user,
            BookingInvitationNotification::class,
        );
    });

    test('handles missing caregiver user gracefully', function () {
        Notification::fake();

        [$booking, $caregiver] = invitedBooking();
        $caregiver->user->delete();

        event(new BookingInvitationSent($booking, $caregiver));

        Notification::assertNothingSent();
    });

    test('database payload contains correct data', function () {
        [$booking, $caregiver] = invitedBooking();
        $caregiverUser = $caregiver->user;

        $notification = new BookingInvitationNotification($booking);
        $payload = $notification->toArray($caregiverUser);

        expect($payload['booking_id'])->toBe($booking->id);
        expect($payload['title'])->toBe('New Job Invitation');
        expect($payload['type'])->toBe('booking_invitation');
        expect($payload['message'])->toContain($booking->client->first_name);
    });

    test('mail returns CaregiverBookingInvitationMail', function () {
        [$booking] = invitedBooking();

        $notification = new BookingInvitationNotification($booking);
        $mail = $notification->toMail($booking->client->user);

        expect($mail)->toBeInstanceOf(CaregiverBookingInvitationMail::class);
        expect($mail->booking->id)->toBe($booking->id);
    });
});
