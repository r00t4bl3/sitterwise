<?php

use App\Enums\CaregiverStatus;
use App\Models\Caregiver;
use App\Models\CaregiverApplication;
use App\Models\CaregiverInterview;
use App\Models\InterviewTalkingPoint;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
        CertificationTypeSeeder::class,
    ]);

    $this->admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

    $user = User::factory()->create(['role' => 'caregiver', 'email' => 'applicant@example.com']);
    $this->caregiver = Caregiver::factory()->create([
        'user_id' => $user->id,
        'status' => CaregiverStatus::InterviewScheduled,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $this->application = CaregiverApplication::create([
        'caregiver_id' => $this->caregiver->id,
        'data' => ['personal' => ['first_name' => 'Jane', 'last_name' => 'Doe']],
        'submitted_at' => now(),
    ]);

    $this->interview = CaregiverInterview::create([
        'caregiver_id' => $this->caregiver->id,
        'evaluator_id' => $this->admin->id,
        'application_id' => $this->application->id,
        'scores' => ['soft_skills' => [], 'professionalism' => []],
        'status' => 'draft',
    ]);

    $this->talkingPointUrl = fn () => "/applications/{$this->application->id}/interview/talking-points";
});

it('can list talking points for an interview', function () {
    $this->interview->talkingPoints()->create([
        'talking_point_id' => null,
        'label' => 'Ask about experience',
        'sort_order' => 0,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(($this->talkingPointUrl)());

    $response->assertSuccessful();
    $response->assertJsonCount(1);
});

it('seeds talking points from master template on first load', function () {
    InterviewTalkingPoint::create(['label' => 'Template point 1', 'sort_order' => 0]);
    InterviewTalkingPoint::create(['label' => 'Template point 2', 'sort_order' => 1]);

    $response = $this->actingAs($this->admin)
        ->get(($this->talkingPointUrl)());

    $response->assertSuccessful();
    $response->assertJsonCount(2);
    $this->assertDatabaseHas('caregiver_interview_talking_points', [
        'caregiver_interview_id' => $this->interview->id,
        'label' => 'Template point 1',
    ]);
});

it('can toggle a talking point', function () {
    $point = $this->interview->talkingPoints()->create([
        'talking_point_id' => null,
        'label' => 'Test point',
        'sort_order' => 0,
    ]);

    $response = $this->actingAs($this->admin)
        ->patch("/applications/{$this->application->id}/interview/talking-points/{$point->id}");

    $response->assertSuccessful();
    $this->assertDatabaseHas('caregiver_interview_talking_points', [
        'id' => $point->id,
        'is_checked' => true,
    ]);

    $response = $this->actingAs($this->admin)
        ->patch("/applications/{$this->application->id}/interview/talking-points/{$point->id}");

    $response->assertSuccessful();
    $this->assertDatabaseHas('caregiver_interview_talking_points', [
        'id' => $point->id,
        'is_checked' => false,
    ]);
});

it('can add a custom talking point', function () {
    $response = $this->actingAs($this->admin)
        ->post(($this->talkingPointUrl)(), ['label' => 'Custom question']);

    $response->assertSuccessful();
    $this->assertDatabaseHas('caregiver_interview_talking_points', [
        'caregiver_interview_id' => $this->interview->id,
        'label' => 'Custom question',
        'talking_point_id' => null,
    ]);
});

it('can update a talking point label and notes', function () {
    $point = $this->interview->talkingPoints()->create([
        'talking_point_id' => null,
        'label' => 'Old label',
        'sort_order' => 0,
    ]);

    $response = $this->actingAs($this->admin)
        ->put("/applications/{$this->application->id}/interview/talking-points/{$point->id}", [
            'label' => 'Updated label',
            'notes' => 'Important note',
        ]);

    $response->assertSuccessful();
    $this->assertDatabaseHas('caregiver_interview_talking_points', [
        'id' => $point->id,
        'label' => 'Updated label',
        'notes' => 'Important note',
    ]);
});

it('can remove a talking point', function () {
    $point = $this->interview->talkingPoints()->create([
        'talking_point_id' => null,
        'label' => 'To remove',
        'sort_order' => 0,
    ]);

    $response = $this->actingAs($this->admin)
        ->delete("/applications/{$this->application->id}/interview/talking-points/{$point->id}");

    $response->assertSuccessful();
    $this->assertDatabaseMissing('caregiver_interview_talking_points', ['id' => $point->id]);
});

it('cannot toggle talking point from other interview', function () {
    $otherUser = User::factory()->create(['role' => 'caregiver', 'email' => 'other@example.com']);
    $otherCaregiver = Caregiver::factory()->create([
        'user_id' => $otherUser->id,
        'status' => CaregiverStatus::InterviewScheduled,
    ]);
    $otherApplication = CaregiverApplication::create([
        'caregiver_id' => $otherCaregiver->id,
        'data' => [],
        'submitted_at' => now(),
    ]);
    $otherInterview = CaregiverInterview::create([
        'caregiver_id' => $otherCaregiver->id,
        'evaluator_id' => $this->admin->id,
        'application_id' => $otherApplication->id,
        'scores' => ['soft_skills' => [], 'professionalism' => []],
        'status' => 'draft',
    ]);
    $otherPoint = $otherInterview->talkingPoints()->create([
        'talking_point_id' => null,
        'label' => 'Other point',
        'sort_order' => 0,
    ]);

    $response = $this->actingAs($this->admin)
        ->patch("/applications/{$this->application->id}/interview/talking-points/{$otherPoint->id}");

    $response->assertNotFound();
});

it('interview create page returns talkingPoints prop', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('applications.interview', $this->application));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/interviews/evaluate')
        ->has('talkingPoints')
    );
});
