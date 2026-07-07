<?php

use App\Mail\AdminBookingCancelledMail;
use App\Mail\AdminGroupBookingCreatedMail;
use App\Mail\AdminPaymentFailedMail;
use App\Mail\BookingReviewReminderMail;
use App\Mail\CaregiverBookingCancelledMail;
use App\Mail\CaregiverBookingInvitationMail;
use App\Mail\ClientBookingCancelledMail;
use App\Mail\ClientGroupBookingCreatedMail;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;

beforeEach(function () {
    $this->seed([
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
});

function bookingsBatchBooking(): Booking
{
    $client = Client::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);
    $caregiver = Caregiver::factory()->create(['first_name' => 'Carla', 'last_name' => 'Sitter']);
    $group = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'service_type' => 'babysitter',
        'client_first_name' => 'Jane',
        'client_last_name' => 'Doe',
        'client_phone' => '+16195551212',
        'location_type' => 'private_home',
        'address_line1' => '123 Main St',
        'address_city' => 'San Diego',
    ]);

    return Booking::factory()->create([
        'booking_group_id' => $group->id,
        'caregiver_id' => $caregiver->id,
        'start_datetime' => now()->addDays(3)->setTime(14, 0),
        'end_datetime' => now()->addDays(3)->setTime(18, 0),
        'total_amount' => 1234.5,
    ]);
}

function bookingsBatchGroup(): BookingGroup
{
    $client = Client::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);
    $group = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'service_type' => 'babysitter',
        'client_first_name' => 'Jane',
        'client_last_name' => 'Doe',
        'client_email' => 'jane@example.com',
        'client_phone' => '+16195551212',
        'location_type' => 'private_home',
        'address_line1' => '123 Main St',
        'address_city' => 'San Diego',
    ]);
    Booking::factory()->count(2)->create(['booking_group_id' => $group->id]);

    return $group->fresh(['bookings']);
}

function la(Booking $booking): array
{
    $start = $booking->start_datetime->copy()->setTimezone('America/Los_Angeles');

    return [
        'start_date' => $start->format('l, F j, Y'),
        'start_time' => $start->format('g:i A'),
        'start_datetime' => $start->format('M j, Y g:i A'),
    ];
}

describe('Booking emails → branded SendGrid templates', function () {
    test('caregiver booking invitation', function () {
        $booking = bookingsBatchBooking();
        $payload = captureSendGridPayload(new CaregiverBookingInvitationMail($booking));
        $data = $payload['personalizations'][0]['dynamic_template_data'];

        expect($payload['template_id'])->toBe('d-aac404a830334ae884098a75cb32caca');
        expect($data)->toMatchArray([
            'client_first_name' => 'Jane',
            'client_last_name' => 'Doe',
            'start_datetime' => la($booking)['start_datetime'],
            'job_url' => route('jobs.short', $booking),
            'address_line1' => '123 Main St',
            'address_city' => 'San Diego',
        ]);
    });

    test('client booking cancelled — includes reason when given, team-BCC eligible', function () {
        config(['mail.team_bcc' => 'hello@sitterwise.com']);
        $booking = bookingsBatchBooking();
        $payload = captureSendGridPayload(new ClientBookingCancelledMail($booking, 'Client requested'));
        $data = $payload['personalizations'][0]['dynamic_template_data'];

        expect($payload['template_id'])->toBe('d-965c67b476c54002b0912d87f5805303');
        expect($data)->toMatchArray([
            'booking_id' => $booking->id,
            'client_first_name' => 'Jane',
            'service_type' => 'Babysitter',
            'start_date' => la($booking)['start_date'],
            'start_time' => la($booking)['start_time'],
            'reason' => 'Client requested',
        ]);
        expect($payload['personalizations'][0]['bcc'][0]['email'])->toBe('hello@sitterwise.com');
    });

    test('client booking cancelled — omits reason when blank', function () {
        $booking = bookingsBatchBooking();
        $payload = captureSendGridPayload(new ClientBookingCancelledMail($booking, ''));

        expect($payload['personalizations'][0]['dynamic_template_data'])->not->toHaveKey('reason');
    });

    test('caregiver booking cancelled', function () {
        $booking = bookingsBatchBooking();
        $payload = captureSendGridPayload(new CaregiverBookingCancelledMail($booking, 'Reassigned'));
        $data = $payload['personalizations'][0]['dynamic_template_data'];

        expect($payload['template_id'])->toBe('d-286f15d2045541babef403f5fde86cef');
        expect($data)->toMatchArray([
            'booking_id' => $booking->id,
            'caregiver_first_name' => 'Carla',
            'service_type' => 'Babysitter',
            'reason' => 'Reassigned',
        ]);
    });

    test('admin booking cancelled — admin gets no team BCC', function () {
        config(['mail.team_bcc' => 'hello@sitterwise.com']);
        $booking = bookingsBatchBooking();
        $canceller = User::factory()->create(['name' => 'Admin Amy']);
        $payload = captureSendGridPayload(new AdminBookingCancelledMail($booking, 'No longer needed', $canceller));
        $data = $payload['personalizations'][0]['dynamic_template_data'];

        expect($payload['template_id'])->toBe('d-71b39db4e170449fba2de7234e8d5961');
        expect($data)->toMatchArray([
            'booking_id' => $booking->id,
            'cancelled_by' => 'Admin Amy',
            'client_name' => 'Jane Doe',
            'caregiver_name' => 'Carla Sitter',
            'booking_url' => url('/bookings/'.$booking->id),
        ]);
        expect($payload['personalizations'][0])->not->toHaveKey('bcc');
    });

    test('client group booking created — bookings array + first-booking number', function () {
        $group = bookingsBatchGroup();
        $payload = captureSendGridPayload(new ClientGroupBookingCreatedMail($group));
        $data = $payload['personalizations'][0]['dynamic_template_data'];

        expect($payload['template_id'])->toBe('d-9304fcd2ccf046e6913979cdfbb7a6c5');
        expect($data['client_first_name'])->toBe('Jane');
        expect($data['service_type'])->toBe('Babysitter');
        expect($data['booking_number'])->toBe($group->bookings->first()->ulid);
        expect($data['bookings'])->toHaveCount(2);
        expect($data['bookings'][0])->toHaveKeys(['date', 'start_time', 'end_time']);
    });

    test('admin group booking created — adds count + last name, no team BCC', function () {
        config(['mail.team_bcc' => 'hello@sitterwise.com']);
        $group = bookingsBatchGroup();
        $payload = captureSendGridPayload(new AdminGroupBookingCreatedMail($group));
        $data = $payload['personalizations'][0]['dynamic_template_data'];

        expect($payload['template_id'])->toBe('d-0574fc2a4e9c44eb9ae3038495fb7b6b');
        expect($data)->toMatchArray([
            'client_first_name' => 'Jane',
            'client_last_name' => 'Doe',
            'booking_count' => 2,
            'client_email' => 'jane@example.com',
        ]);
        expect($payload['personalizations'][0])->not->toHaveKey('bcc');
    });

    test('review reminder', function () {
        $booking = bookingsBatchBooking();
        $payload = captureSendGridPayload(new BookingReviewReminderMail($booking, 'https://sitterwise.test/review'));
        $data = $payload['personalizations'][0]['dynamic_template_data'];

        expect($payload['template_id'])->toBe('d-ed4e08ffb28648f4aee1485389653810');
        expect($data)->toBe([
            'caregiver_name' => 'Carla Sitter',
            'service_date' => $booking->end_datetime->copy()->setTimezone('America/Los_Angeles')->format('l, F j, Y'),
            'review_url' => 'https://sitterwise.test/review',
        ]);
    });

    test('admin payment failed — money formatted, no team BCC', function () {
        config(['mail.team_bcc' => 'hello@sitterwise.com']);
        $booking = bookingsBatchBooking();
        $booking->total_amount = 1234.5;
        $payload = captureSendGridPayload(new AdminPaymentFailedMail($booking, 2, 'Card declined'));
        $data = $payload['personalizations'][0]['dynamic_template_data'];

        expect($payload['template_id'])->toBe('d-ffd19317faa641ac83e898f159ed7692');
        expect($data)->toMatchArray([
            'error_message' => 'Card declined',
            'booking_id' => $booking->id,
            'client_name' => 'Jane Doe',
            'service_type' => 'Babysitter',
            'attempt_count' => 2,
            'total_amount' => '1,234.50',
        ]);
        expect($payload['personalizations'][0])->not->toHaveKey('bcc');
    });
});

describe('Booking emails still render their Blade body locally', function () {
    test('the dual-mode booking emails render without error', function (string $type) {
        expect(config('mail.default'))->not->toBe('sendgrid');

        $mailable = match ($type) {
            'invitation' => new CaregiverBookingInvitationMail(bookingsBatchBooking()),
            'admin-group' => new AdminGroupBookingCreatedMail(bookingsBatchGroup()),
            'review' => new BookingReviewReminderMail(bookingsBatchBooking(), 'https://sitterwise.test/review'),
            'payment-failed' => new AdminPaymentFailedMail(bookingsBatchBooking(), 2, 'Card declined'),
        };

        expect($mailable->render())->toBeString()->not->toBe('')->toContain('<');
    })->with(['invitation', 'admin-group', 'review', 'payment-failed']);
});
