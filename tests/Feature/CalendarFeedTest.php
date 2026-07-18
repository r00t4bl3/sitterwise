<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\User;
use App\Services\CalendarFeedService;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        CertificationTypeSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
});

function caregiverWithFeedToken(): Caregiver
{
    $caregiver = Caregiver::factory()->create();
    $caregiver->forceFill(['calendar_feed_token' => Str::random(32)])->save();

    return $caregiver;
}

test('returns an ics calendar for a valid token', function () {
    $caregiver = caregiverWithFeedToken();
    Booking::factory()->create([
        'caregiver_id' => $caregiver->id,
        'status' => BookingStatus::Confirmed->value,
        'start_datetime' => now()->addDay(),
        'end_datetime' => now()->addDay()->addHours(4),
    ]);

    $response = $this->get("/calendar/feed/{$caregiver->calendar_feed_token}.ics");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/calendar');
    expect($response->getContent())
        ->toContain('BEGIN:VCALENDAR')
        ->toContain('BEGIN:VEVENT');
});

test('returns 404 for an invalid token', function () {
    $this->get('/calendar/feed/'.Str::random(32).'.ics')->assertNotFound();
});

test('includes only upcoming confirmed bookings', function () {
    $caregiver = caregiverWithFeedToken();

    $confirmed = Booking::factory()->create([
        'caregiver_id' => $caregiver->id,
        'status' => BookingStatus::Confirmed->value,
        'start_datetime' => now()->addDay(),
        'end_datetime' => now()->addDay()->addHours(4),
    ]);

    // Should all be excluded.
    Booking::factory()->create([
        'caregiver_id' => $caregiver->id,
        'status' => BookingStatus::Received->value,
        'start_datetime' => now()->addDays(2),
        'end_datetime' => now()->addDays(2)->addHours(4),
    ]);
    Booking::factory()->create([
        'caregiver_id' => $caregiver->id,
        'status' => BookingStatus::Completed->value,
        'start_datetime' => now()->addDays(3),
        'end_datetime' => now()->addDays(3)->addHours(4),
    ]);
    Booking::factory()->create([
        'caregiver_id' => $caregiver->id,
        'status' => BookingStatus::Cancelled->value,
        'start_datetime' => now()->addDays(4),
        'end_datetime' => now()->addDays(4)->addHours(4),
    ]);
    // Past confirmed — excluded by the upcoming filter.
    Booking::factory()->create([
        'caregiver_id' => $caregiver->id,
        'status' => BookingStatus::Confirmed->value,
        'start_datetime' => now()->subDays(2),
        'end_datetime' => now()->subDays(2)->addHours(4),
    ]);

    $body = $this->get("/calendar/feed/{$caregiver->calendar_feed_token}.ics")->getContent();

    expect($body)->toContain("booking-{$confirmed->ulid}@sitterwise.com");
    expect(substr_count($body, 'BEGIN:VEVENT'))->toBe(1);
});

test('does not leak other caregivers bookings', function () {
    $caregiver = caregiverWithFeedToken();
    $other = caregiverWithFeedToken();

    $otherBooking = Booking::factory()->create([
        'caregiver_id' => $other->id,
        'status' => BookingStatus::Confirmed->value,
        'start_datetime' => now()->addDay(),
        'end_datetime' => now()->addDay()->addHours(4),
    ]);

    $body = $this->get("/calendar/feed/{$caregiver->calendar_feed_token}.ics")->getContent();

    expect($body)->not->toContain("booking-{$otherBooking->ulid}@sitterwise.com");
    expect(substr_count($body, 'BEGIN:VEVENT'))->toBe(0);
});

test('emits event times in the business timezone, not raw UTC (regression)', function () {
    // Mock a UTC instant to match production (config app timezone is UTC with no
    // test-now); a non-UTC test-now would make Eloquent's datetime cast misread
    // the stored UTC value, which is a test artifact, not production behaviour.
    Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00'));
    $caregiver = caregiverWithFeedToken();

    // 2026-08-01 02:00 UTC = 2026-07-31 19:00 Pacific (PDT, UTC-7).
    Booking::factory()->create([
        'caregiver_id' => $caregiver->id,
        'status' => BookingStatus::Confirmed->value,
        'start_datetime' => '2026-08-01T02:00:00Z',
        'end_datetime' => '2026-08-01T06:00:00Z',
    ]);

    $body = $this->get("/calendar/feed/{$caregiver->calendar_feed_token}.ics")->getContent();

    expect($body)->toContain('America/Los_Angeles');
    // Correct Pacific wall-clock. The floating-local bug would emit 20260801T020000.
    expect($body)->toContain('20260731T190000');
    expect($body)->not->toContain('20260801T020000');

    Carbon::setTestNow();
});

test('returns a valid empty calendar when there are no upcoming jobs', function () {
    $caregiver = caregiverWithFeedToken();

    $response = $this->get("/calendar/feed/{$caregiver->calendar_feed_token}.ics");

    $response->assertOk();
    expect($response->getContent())
        ->toContain('BEGIN:VCALENDAR')
        ->toContain('END:VCALENDAR')
        ->not->toContain('BEGIN:VEVENT');
});

test('regenerating the token invalidates the old feed url', function () {
    $caregiver = caregiverWithFeedToken();
    $old = $caregiver->calendar_feed_token;

    $this->get("/calendar/feed/{$old}.ics")->assertOk();

    $new = app(CalendarFeedService::class)->regenerateToken($caregiver);

    expect($new)->not->toBe($old);
    $this->get("/calendar/feed/{$old}.ics")->assertNotFound();
    $this->get("/calendar/feed/{$new}.ics")->assertOk();
});

test('a caregiver sees their calendar feed url on the settings page', function () {
    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get('/settings/caregiver/calendar-sync')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/calendar-sync')
            ->where('feedUrl', fn ($url) => str_contains(
                $url,
                $caregiver->fresh()->calendar_feed_token,
            ))
        );
});

test('a non-caregiver is redirected away from calendar sync settings', function () {
    $user = User::factory()->create(['role' => 'client']);

    $this->actingAs($user)->get('/settings/caregiver/calendar-sync')
        ->assertRedirect(route('profile.edit'));
});
