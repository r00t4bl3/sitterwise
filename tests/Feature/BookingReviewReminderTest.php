<?php

use App\Enums\BookingStatus;
use App\Models\AttributeDefinition;
use App\Models\Booking;
use App\Models\BookingRating;
use App\Models\Caregiver;
use App\Models\CertificationType;
use App\Models\Client;
use App\Models\Location;
use App\Models\SpecialtyType;
use App\Models\User;
use App\Notifications\BookingReviewReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    SpecialtyType::factory()->count(5)->create(['is_active' => true]);
    Location::factory()->count(5)->create(['is_active' => true]);
    AttributeDefinition::factory()->count(5)->create(['is_active' => true, 'entity_type' => 'caregiver']);
    CertificationType::factory()->count(5)->create(['is_active' => true]);

    $this->clientUser = User::factory()->create(['role' => 'client']);
    $this->client = Client::factory()->create(['user_id' => $this->clientUser->id]);

    $this->caregiverUser = User::factory()->create(['role' => 'caregiver']);
    $this->caregiver = Caregiver::factory()->create([
        'user_id' => $this->caregiverUser->id,
    ]);
});

test('command sends email reminder for bookings completed 2+ hours ago', function () {
    $booking = Booking::factory()->forClient($this->client)->create([
        'caregiver_id' => $this->caregiver->id,
        'status' => BookingStatus::Completed->value,
        'end_datetime' => now()->subHours(4),
    ]);

    Notification::fake();

    $this->artisan('app:send-review-reminders')
        ->assertSuccessful();

    Notification::assertSentTo(
        $this->clientUser,
        BookingReviewReminderNotification::class,
    );
});

test('command skips bookings with existing caregiver rating', function () {
    $booking = Booking::factory()->forClient($this->client)->create([
        'caregiver_id' => $this->caregiver->id,
        'status' => BookingStatus::Completed->value,
        'end_datetime' => now()->subHours(4),
    ]);

    BookingRating::create([
        'booking_id' => $booking->id,
        'rater_id' => $this->clientUser->id,
        'ratable_type' => Caregiver::class,
        'ratable_id' => $this->caregiver->id,
        'rating' => 5,
    ]);

    Notification::fake();

    $this->artisan('app:send-review-reminders')
        ->assertSuccessful();

    Notification::assertNothingSent();
});

test('command skips bookings completed less than 2 hours ago', function () {
    Booking::factory()->forClient($this->client)->create([
        'caregiver_id' => $this->caregiver->id,
        'status' => BookingStatus::Completed->value,
        'end_datetime' => now()->subHour(),
    ]);

    Notification::fake();

    $this->artisan('app:send-review-reminders')
        ->assertSuccessful();

    Notification::assertNothingSent();
});

test('command skips bookings without a client user', function () {
    $orphanClient = Client::factory()->create([
        'user_id' => User::factory()->create(['role' => 'client'])->id,
    ]);

    $booking = Booking::factory()->forClient($orphanClient)->create([
        'caregiver_id' => $this->caregiver->id,
        'status' => BookingStatus::Completed->value,
        'end_datetime' => now()->subHours(4),
    ]);

    $orphanClient->user->delete();

    Notification::fake();

    $this->artisan('app:send-review-reminders')
        ->assertSuccessful();

    Notification::assertNothingSent();
});

test('command does not resend the email reminder on a second run', function () {
    $booking = Booking::factory()->forClient($this->client)->create([
        'caregiver_id' => $this->caregiver->id,
        'status' => BookingStatus::Completed->value,
        'end_datetime' => now()->subHours(4),
    ]);

    Notification::fake();

    $this->artisan('app:send-review-reminders')->assertSuccessful();
    $this->artisan('app:send-review-reminders')->assertSuccessful();

    Notification::assertSentToTimes(
        $this->clientUser,
        BookingReviewReminderNotification::class,
        1,
    );

    expect($booking->fresh()->review_reminder_email_sent_at)->not->toBeNull();
});

test('command does not resend the SMS reminder on a second run', function () {
    $booking = Booking::factory()->forClient($this->client)->create([
        'caregiver_id' => $this->caregiver->id,
        'status' => BookingStatus::Completed->value,
        'end_datetime' => now()->subHours(50),
    ]);

    Notification::fake();

    $this->artisan('app:send-review-reminders')->assertSuccessful();
    $this->artisan('app:send-review-reminders')->assertSuccessful();

    Notification::assertSentToTimes(
        $this->clientUser,
        BookingReviewReminderNotification::class,
        1,
    );

    expect($booking->fresh()->review_reminder_sms_sent_at)->not->toBeNull();
});

test('command sends SMS for bookings completed 48+ hours ago', function () {
    $booking = Booking::factory()->forClient($this->client)->create([
        'caregiver_id' => $this->caregiver->id,
        'status' => BookingStatus::Completed->value,
        'end_datetime' => now()->subHours(50),
    ]);

    Notification::fake();

    $this->artisan('app:send-review-reminders')
        ->assertSuccessful();

    Notification::assertSentTo(
        $this->clientUser,
        BookingReviewReminderNotification::class,
    );
});
