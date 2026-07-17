<?php

use App\Enums\CaregiverStatus;
use App\Mail\CaregiverBirthdayMail;
use App\Models\Caregiver;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function birthdayCaregiver(User $user, CarbonInterface $dob, CaregiverStatus $status = CaregiverStatus::Active): Caregiver
{
    return Caregiver::create([
        'user_id' => $user->id,
        'status' => $status->value,
        'first_name' => 'Birthday',
        'last_name' => 'Person',
        'slug' => 'bday-'.uniqid(),
        'phone' => '619-555-0100',
        'date_of_birth' => $dob->format('Y-m-d'),
    ]);
}

test('emails an active caregiver whose birthday is today and records the year', function () {
    Mail::fake();
    $user = User::factory()->create(['role' => 'caregiver', 'email' => 'bday@example.com']);
    $caregiver = birthdayCaregiver($user, now()->subYears(30));

    $this->artisan('caregivers:send-birthday-greetings')->assertSuccessful();

    Mail::assertQueued(
        CaregiverBirthdayMail::class,
        fn ($mail) => $mail->hasTo('bday@example.com'),
    );
    expect($caregiver->fresh()->last_birthday_greeted_year)->toBe(now()->year);
});

test('does not email a caregiver whose birthday is not today', function () {
    Mail::fake();
    $user = User::factory()->create(['role' => 'caregiver']);
    birthdayCaregiver($user, now()->addDay()->subYears(30));

    $this->artisan('caregivers:send-birthday-greetings')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('does not email an inactive caregiver on their birthday', function () {
    Mail::fake();
    $user = User::factory()->create(['role' => 'caregiver']);
    birthdayCaregiver($user, now()->subYears(30), CaregiverStatus::Inactive);

    $this->artisan('caregivers:send-birthday-greetings')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('greets a caregiver only once per year even if the command runs again', function () {
    Mail::fake();
    $user = User::factory()->create(['role' => 'caregiver', 'email' => 'once@example.com']);
    birthdayCaregiver($user, now()->subYears(30));

    $this->artisan('caregivers:send-birthday-greetings')->assertSuccessful();
    $this->artisan('caregivers:send-birthday-greetings')->assertSuccessful();

    Mail::assertQueued(CaregiverBirthdayMail::class, 1);
});
