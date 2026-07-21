<?php

use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Events\BookingInvitationSent;
use App\Listeners\SendBookingInvitationNotifications;
use App\Mail\CaregiverBookingInvitationMail;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\PricingRule;
use App\Notifications\BookingInvitationNotification;
use App\Support\Settings;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SettingsSeeder;
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
        SettingsSeeder::class,
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

    // Pin lifesaver_override=false so invite-text assertions are deterministic —
    // the factory's default start_datetime can otherwise land inside the
    // short-notice window and flip isLifesaver() on at random.
    $booking = Booking::factory()->forClient($client)->create(['lifesaver_override' => false]);

    return [$booking, $caregiver, $client];
}

function invitedBookingWithGroup(array $children = [], array $groupOverrides = []): array
{
    [$booking, $caregiver, $client] = invitedBooking();

    $group = BookingGroup::factory()->create(array_merge([
        'client_id' => $client->id,
        'address_city' => 'San Diego',
        'children' => $children ?: [
            ['birth_year' => now()->subYears(4)->year, 'name' => 'Alex'],
            ['birth_year' => now()->subYears(7)->year, 'name' => 'Sam'],
        ],
    ], $groupOverrides));

    $booking->update(['booking_group_id' => $group->id]);

    return [$booking->fresh(), $caregiver, $client, $group];
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

    test('invitation email renders the short job link and not the dead available route', function () {
        [$booking] = invitedBooking();

        $rendered = (new CaregiverBookingInvitationMail($booking))->render();

        expect($rendered)->toContain(route('jobs.short', $booking))
            ->and($rendered)->not->toContain('/bookings/available');
    });

    describe('toSms', function () {
        test('private home format', function () {
            [$booking, $caregiver] = invitedBookingWithGroup();

            $result = (new BookingInvitationNotification($booking))->toSms($caregiver->user);

            expect($result->message)->toMatch('/^New job – /');
            expect($result->message)->toContain('San Diego · 2 children (4 & 7)');
            expect($result->message)->toContain('View & claim:');
            expect($result->message)->toContain("\n");
        });

        test('hotel format', function () {
            [$booking, $caregiver] = invitedBookingWithGroup(
                children: [['birth_year' => now()->subYears(3)->year, 'name' => 'Mia']],
                groupOverrides: ['location_type' => LocationType::Hotel->value, 'hotel_name' => 'Hotel del Coronado'],
            );

            $result = (new BookingInvitationNotification($booking))->toSms($caregiver->user);

            expect($result->message)->toContain('Hotel del Coronado · 1 child (3)');
        });

        test('child with birth_year 0 does not report a bogus age', function () {
            [$booking, $caregiver] = invitedBookingWithGroup(
                children: [['birth_year' => 0, 'name' => 'Baby']],
            );

            $result = (new BookingInvitationNotification($booking))->toSms($caregiver->user);

            expect($result->message)->not->toContain('2026')
                ->and($result->message)->toContain('1 child');
        });

        test('singular child label', function () {
            [$booking, $caregiver] = invitedBookingWithGroup(
                children: [['birth_year' => now()->subYears(5)->year, 'name' => 'Jordan']],
            );

            $result = (new BookingInvitationNotification($booking))->toSms($caregiver->user);

            expect($result->message)->toContain('1 child (5)');
        });

        test('plural children label', function () {
            [$booking, $caregiver] = invitedBookingWithGroup(
                children: [
                    ['birth_year' => now()->subYears(3)->year, 'name' => 'A'],
                    ['birth_year' => now()->subYears(5)->year, 'name' => 'B'],
                    ['birth_year' => now()->subYears(8)->year, 'name' => 'C'],
                ],
            );

            $result = (new BookingInvitationNotification($booking))->toSms($caregiver->user);

            expect($result->message)->toContain('3 children (3, 5 & 8)')
                ->and($result->message)->not->toContain('3 & 5 & 8');
        });

        test('under 160 character budget', function () {
            [$booking, $caregiver] = invitedBookingWithGroup();

            $result = (new BookingInvitationNotification($booking))->toSms($caregiver->user);

            expect(mb_strlen($result->message))->toBeLessThanOrEqual(160);
        });

        test('contains short link', function () {
            [$booking, $caregiver] = invitedBookingWithGroup();

            $result = (new BookingInvitationNotification($booking))->toSms($caregiver->user);

            expect($result->message)->toContain(route('jobs.short', $booking));
        });

        test('multi-day date range same month', function () {
            [$booking, $caregiver, , $group] = invitedBookingWithGroup(
                children: [['birth_year' => now()->subYears(4)->year, 'name' => 'Alex']],
            );

            $tz = 'America/Los_Angeles';
            $booking->updateQuietly([
                'start_datetime' => now($tz)->setTime(9, 0),
                'end_datetime' => now($tz)->setTime(17, 0),
            ]);

            Booking::factory()->forClient($booking->client)->create([
                'booking_group_id' => $group->id,
                'start_datetime' => now($tz)->addDays(1)->setTime(9, 0),
                'end_datetime' => now($tz)->addDays(1)->setTime(17, 0),
            ]);
            Booking::factory()->forClient($booking->client)->create([
                'booking_group_id' => $group->id,
                'start_datetime' => now($tz)->addDays(2)->setTime(9, 0),
                'end_datetime' => now($tz)->addDays(2)->setTime(17, 0),
            ]);

            $result = (new BookingInvitationNotification($booking))->toSms($caregiver->user);

            expect($result->message)->toMatch('/^New job – \w{3} \d{1,2}\/\d{1,2}–\w{3} \d{1,2}\/\d{1,2}, /')
                ->and($result->message)->toContain('daily')
                ->and($result->message)->toContain('9:00am–5:00pm');
        });

        test('multi-day overnight', function () {
            [$booking, $caregiver, , $group] = invitedBookingWithGroup(
                children: [['birth_year' => now()->subYears(4)->year, 'name' => 'Alex']],
            );

            $tz = 'America/Los_Angeles';
            $booking->updateQuietly([
                'start_datetime' => now($tz)->setTime(22, 0),
                'end_datetime' => now($tz)->addDay()->setTime(6, 0),
            ]);

            Booking::factory()->forClient($booking->client)->create([
                'booking_group_id' => $group->id,
                'start_datetime' => now($tz)->addDays(1)->setTime(22, 0),
                'end_datetime' => now($tz)->addDays(2)->setTime(6, 0),
            ]);
            Booking::factory()->forClient($booking->client)->create([
                'booking_group_id' => $group->id,
                'start_datetime' => now($tz)->addDays(2)->setTime(22, 0),
                'end_datetime' => now($tz)->addDays(3)->setTime(6, 0),
            ]);

            $result = (new BookingInvitationNotification($booking))->toSms($caregiver->user);

            expect($result->message)->toMatch('/^New job –.*, overnight/');
        });
    });

    describe('lifesaver invites', function () {
        test('sms leads with the lifesaver bonus badge, details follow', function () {
            [$booking, $caregiver] = invitedBookingWithGroup();
            $booking->update(['lifesaver_override' => true]);

            $result = (new BookingInvitationNotification($booking))->toSms($caregiver->user);

            expect($result->message)->toStartWith('LIFESAVER JOB, $15 BONUS:')
                ->and($result->message)->toContain('· 2 children (4 & 7)')
                ->and($result->message)->toContain('View & claim:')
                ->and($result->message)->not->toContain('New job –');
        });

        test('non-lifesaver sms is unchanged (New job label)', function () {
            [$booking, $caregiver] = invitedBookingWithGroup();

            $result = (new BookingInvitationNotification($booking))->toSms($caregiver->user);

            expect($result->message)->toStartWith('New job – ')
                ->and($result->message)->not->toContain('LIFESAVER JOB');
        });

        test('multi-day lifesaver sms also leads with the badge', function () {
            [$booking, $caregiver, , $group] = invitedBookingWithGroup(
                children: [['birth_year' => now()->subYears(4)->year, 'name' => 'Alex']],
            );

            $tz = 'America/Los_Angeles';
            $booking->updateQuietly([
                'start_datetime' => now($tz)->setTime(9, 0),
                'end_datetime' => now($tz)->setTime(17, 0),
            ]);
            Booking::factory()->forClient($booking->client)->create([
                'booking_group_id' => $group->id,
                'start_datetime' => now($tz)->addDays(1)->setTime(9, 0),
                'end_datetime' => now($tz)->addDays(1)->setTime(17, 0),
            ]);
            $booking->update(['lifesaver_override' => true]);

            $result = (new BookingInvitationNotification($booking))->toSms($caregiver->user);

            expect($result->message)->toStartWith('LIFESAVER JOB, $15 BONUS:');
        });

        test('web push title advertises the bonus', function () {
            [$booking, $caregiver] = invitedBookingWithGroup();
            $booking->update(['lifesaver_override' => true]);

            $push = (new BookingInvitationNotification($booking))
                ->toWebPush($caregiver->user, (object) [])
                ->toArray();

            expect($push['title'])->toContain('LIFESAVER JOB, $15 BONUS');
        });

        test('in-app payload title and message are prefixed with the badge', function () {
            [$booking, $caregiver] = invitedBookingWithGroup();
            $booking->update(['lifesaver_override' => true]);

            $payload = (new BookingInvitationNotification($booking))->toArray($caregiver->user);

            expect($payload['title'])->toBe('LIFESAVER JOB, $15 BONUS')
                ->and($payload['message'])->toStartWith('LIFESAVER JOB, $15 BONUS: ');
        });

        test('bonus amount reflects the lifesaver.bonus setting', function () {
            Settings::set('lifesaver.bonus', 20);

            [$booking, $caregiver] = invitedBookingWithGroup();
            $booking->update(['lifesaver_override' => true]);

            $result = (new BookingInvitationNotification($booking))->toSms($caregiver->user);

            expect($result->message)->toStartWith('LIFESAVER JOB, $20 BONUS:');
        });
    });
});
