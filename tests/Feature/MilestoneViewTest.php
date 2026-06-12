<?php

use App\Enums\AssignmentResolution;
use App\Enums\CaregiverStatus;
use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Models\BookingGroup;
use App\Models\Caregiver;
use App\Models\CaregiverInternalRating;
use App\Models\CertificationType;
use App\Models\Client;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function milestoneCaregiver(array $overrides = []): Caregiver
{
    $user = User::factory()->create(['role' => 'caregiver']);

    return Caregiver::create(array_merge([
        'user_id' => $user->id,
        'first_name' => 'Miles',
        'last_name' => 'Stone',
        'slug' => 'miles-stone-'.uniqid(),
        'phone' => '619-555-0100',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'date_of_birth' => '2000-01-01',
        'rating' => 4.5,
    ], $overrides));
}

function msBooking(Caregiver $caregiver, CarbonInterface|string|null $endDatetime = null): Booking
{
    $end = $endDatetime ?? now()->subDays(rand(1, 30));

    return Booking::factory()
        ->completed()
        ->create([
            'booking_group_id' => BookingGroup::factory(),
            'caregiver_id' => $caregiver->id,
            'end_datetime' => $end,
        ]);
}

function msNotification(Caregiver $caregiver, bool $claimed = true): void
{
    $booking = Booking::factory()
        ->create([
            'booking_group_id' => BookingGroup::factory(),
            'caregiver_id' => $caregiver->id,
        ]);

    $notifiedAt = now()->subHours(rand(1, 48));

    BookingCaregiverNotification::create([
        'booking_id' => $booking->id,
        'caregiver_id' => $caregiver->id,
        'notified_at' => $notifiedAt,
        'responded_at' => $claimed ? (clone $notifiedAt)->addHours(rand(1, 4)) : null,
        'claimed' => $claimed,
    ]);
}

beforeEach(function () {
    $trustline = new CertificationType(['name' => 'Trustline', 'expires_required' => false]);
    $trustline->id = 3;
    $trustline->save();
});

it('returns 403 for non-caregiver users', function () {
    $client = Client::factory()->create();
    $user = $client->user;

    actingAs($user)->get(route('milestones'))->assertForbidden();
});

it('redirects guests to login', function () {
    $this->get(route('milestones'))->assertRedirect('/login');
});

it('displays milestone stats for a caregiver with no data', function () {
    $caregiver = milestoneCaregiver(['status' => CaregiverStatus::Active]);

    actingAs($caregiver->user)
        ->get(route('milestones'))
        ->assertInertia(fn ($page) => $page
            ->component('caregiver/milestones')
            ->has('milestones', fn ($m) => $m
                ->where('completedJobs', 0)
                ->where('jobStreak', 0)
                ->where('rating', '4.50')
                ->where('ratingCount', 0)
                ->where('reliabilityPercent', null)
                ->where('trustlineCertified', false)
                ->etc()
            )
            ->has('engagement', fn ($e) => $e
                ->where('jobsOffered', 0)
                ->where('jobsAccepted', 0)
                ->where('acceptanceRate', 0)
                ->where('backOutRate', 0)
                ->etc()
            )
        );
});

it('computes completed jobs from bookings', function () {
    $caregiver = milestoneCaregiver(['status' => CaregiverStatus::Active]);

    msBooking($caregiver, now()->subDays(5));
    msBooking($caregiver, now()->subDays(10));
    msBooking($caregiver, now()->subDays(20));

    actingAs($caregiver->user)
        ->get(route('milestones'))
        ->assertInertia(fn ($page) => $page
            ->component('caregiver/milestones')
            ->has('milestones', fn ($m) => $m->where('completedJobs', 3)->etc())
        );
});

it('computes job streak from consecutive completions', function () {
    $caregiver = milestoneCaregiver(['status' => CaregiverStatus::Active]);

    $pastDay = fn ($d) => now()->subDays($d);

    $b1 = msBooking($caregiver, $pastDay(10));
    $b1->assignments()->firstOrCreate(
        ['caregiver_id' => $caregiver->id],
        ['assigned_at' => $pastDay(12)]
    )->resolve(AssignmentResolution::Completed);

    $b2 = msBooking($caregiver, $pastDay(7));
    $b2->assignments()->firstOrCreate(
        ['caregiver_id' => $caregiver->id],
        ['assigned_at' => $pastDay(9)]
    )->resolve(AssignmentResolution::Completed);

    $b3 = msBooking($caregiver, $pastDay(3));
    $b3->assignments()->firstOrCreate(
        ['caregiver_id' => $caregiver->id],
        ['assigned_at' => $pastDay(5)]
    )->resolve(AssignmentResolution::Completed);

    actingAs($caregiver->user)
        ->get(route('milestones'))
        ->assertInertia(fn ($page) => $page
            ->component('caregiver/milestones')
            ->has('milestones', fn ($m) => $m->where('jobStreak', 3)->etc())
        );
});

it('breaks job streak on backed-out assignment', function () {
    $caregiver = milestoneCaregiver(['status' => CaregiverStatus::Active]);

    $pastDay = fn ($d) => now()->subDays($d);

    $b1 = msBooking($caregiver, $pastDay(20));
    $b1->assignments()->firstOrCreate(
        ['caregiver_id' => $caregiver->id],
        ['assigned_at' => $pastDay(22)]
    )->resolve(AssignmentResolution::Completed);

    $b2 = msBooking($caregiver, $pastDay(10));
    $b2->assignments()->firstOrCreate(
        ['caregiver_id' => $caregiver->id],
        ['assigned_at' => $pastDay(12)]
    )->resolve(AssignmentResolution::BackedOut);

    $b3 = msBooking($caregiver, $pastDay(3));
    $b3->assignments()->firstOrCreate(
        ['caregiver_id' => $caregiver->id],
        ['assigned_at' => $pastDay(5)]
    )->resolve(AssignmentResolution::Completed);

    actingAs($caregiver->user)
        ->get(route('milestones'))
        ->assertInertia(fn ($page) => $page
            ->component('caregiver/milestones')
            ->has('milestones', fn ($m) => $m->where('jobStreak', 1)->etc())
        );
});

it('skips reassigned assignments in job streak', function () {
    $caregiver = milestoneCaregiver(['status' => CaregiverStatus::Active]);

    $pastDay = fn ($d) => now()->subDays($d);

    $b1 = msBooking($caregiver, $pastDay(10));
    $b1->assignments()->firstOrCreate(
        ['caregiver_id' => $caregiver->id],
        ['assigned_at' => $pastDay(12)]
    )->resolve(AssignmentResolution::Completed);

    $b2 = msBooking($caregiver, $pastDay(7));
    $b2->assignments()->firstOrCreate(
        ['caregiver_id' => $caregiver->id],
        ['assigned_at' => $pastDay(9)]
    )->resolve(AssignmentResolution::Reassigned);

    $b3 = msBooking($caregiver, $pastDay(3));
    $b3->assignments()->firstOrCreate(
        ['caregiver_id' => $caregiver->id],
        ['assigned_at' => $pastDay(5)]
    )->resolve(AssignmentResolution::Completed);

    actingAs($caregiver->user)
        ->get(route('milestones'))
        ->assertInertia(fn ($page) => $page
            ->component('caregiver/milestones')
            ->has('milestones', fn ($m) => $m->where('jobStreak', 2)->etc())
        );
});

it('computes engagement metrics from notifications', function () {
    $caregiver = milestoneCaregiver(['status' => CaregiverStatus::Active]);

    msNotification($caregiver, true);
    msNotification($caregiver, true);
    msNotification($caregiver, false);

    actingAs($caregiver->user)
        ->get(route('milestones'))
        ->assertInertia(fn ($page) => $page
            ->component('caregiver/milestones')
            ->has('engagement', fn ($e) => $e
                ->where('jobsOffered', 3)
                ->where('jobsAccepted', 2)
                ->where('acceptanceRate', 67)
                ->where('declined', 1)
                ->where('declinedPercent', 33)
                ->etc()
            )
        );
});

it('computes back-out rate from assignments', function () {
    $caregiver = milestoneCaregiver(['status' => CaregiverStatus::Active]);

    $pastDay = fn ($d) => now()->subDays($d);

    $b1 = msBooking($caregiver, $pastDay(10));
    $b1->assignments()->firstOrCreate(
        ['caregiver_id' => $caregiver->id],
        ['assigned_at' => $pastDay(12)]
    )->resolve(AssignmentResolution::Completed);

    $b2 = msBooking($caregiver, $pastDay(7));
    $b2->assignments()->firstOrCreate(
        ['caregiver_id' => $caregiver->id],
        ['assigned_at' => $pastDay(9)]
    )->resolve(AssignmentResolution::BackedOut);

    $b3 = msBooking($caregiver, $pastDay(3));
    $b3->assignments()->firstOrCreate(
        ['caregiver_id' => $caregiver->id],
        ['assigned_at' => $pastDay(5)]
    )->resolve(AssignmentResolution::Completed);

    actingAs($caregiver->user)
        ->get(route('milestones'))
        ->assertInertia(fn ($page) => $page
            ->component('caregiver/milestones')
            ->has('engagement', fn ($e) => $e->where('backOutRate', 33)->etc())
        );
});

it('shows reliability percent from internal rating', function () {
    $caregiver = milestoneCaregiver(['status' => CaregiverStatus::Active]);

    CaregiverInternalRating::create([
        'caregiver_id' => $caregiver->id,
        'reliability_score' => 4.0,
    ]);

    actingAs($caregiver->user)
        ->get(route('milestones'))
        ->assertInertia(fn ($page) => $page
            ->component('caregiver/milestones')
            ->has('milestones', fn ($m) => $m->where('reliabilityPercent', 80)->etc())
        );
});

it('shows trustline section when caregiver has trustline certification', function () {
    $caregiver = milestoneCaregiver(['status' => CaregiverStatus::Active]);

    $trustline = CertificationType::find(3);
    $caregiver->certifications()->attach($trustline->id, ['verified_at' => now()]);

    actingAs($caregiver->user)
        ->get(route('milestones'))
        ->assertInertia(fn ($page) => $page
            ->component('caregiver/milestones')
            ->has('milestones', fn ($m) => $m->where('trustlineCertified', true)->etc())
        );
});

it('hides trustline when caregiver lacks certification', function () {
    $caregiver = milestoneCaregiver(['status' => CaregiverStatus::Active]);

    actingAs($caregiver->user)
        ->get(route('milestones'))
        ->assertInertia(fn ($page) => $page
            ->component('caregiver/milestones')
            ->has('milestones', fn ($m) => $m->where('trustlineCertified', false)->etc())
        );
});
