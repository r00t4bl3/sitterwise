<?php

use App\Models\InterviewTalkingPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'role' => 'super_admin',
        'email_verified_at' => now(),
    ]);
});

it('can list talking points', function () {
    InterviewTalkingPoint::create(['label' => 'Ask about availability', 'sort_order' => 0]);
    InterviewTalkingPoint::create(['label' => 'Verify certifications', 'sort_order' => 1]);

    $response = $this->actingAs($this->user)
        ->get(route('talking-points.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('superadmin/talking-points/Index')
        ->has('talkingPoints', 2)
    );
});

it('can create a talking point', function () {
    $data = [
        'label' => 'Discuss weekend availability',
        'description' => 'Ask if they can work Saturdays and Sundays',
    ];

    $response = $this->actingAs($this->user)
        ->post(route('talking-points.store'), $data);

    $response->assertRedirect();
    $this->assertDatabaseHas('interview_talking_points', [
        'label' => 'Discuss weekend availability',
        'description' => 'Ask if they can work Saturdays and Sundays',
    ]);
});

it('can update a talking point', function () {
    $point = InterviewTalkingPoint::create(['label' => 'Old label', 'sort_order' => 0]);

    $data = [
        'label' => 'Updated label',
        'description' => 'Updated description',
    ];

    $response = $this->actingAs($this->user)
        ->put(route('talking-points.update', $point), $data);

    $response->assertRedirect();
    $this->assertDatabaseHas('interview_talking_points', [
        'id' => $point->id,
        'label' => 'Updated label',
        'description' => 'Updated description',
    ]);
});

it('can delete a talking point', function () {
    $point = InterviewTalkingPoint::create(['label' => 'To delete', 'sort_order' => 0]);

    $response = $this->actingAs($this->user)
        ->delete(route('talking-points.destroy', $point));

    $response->assertRedirect();
    $this->assertDatabaseMissing('interview_talking_points', ['id' => $point->id]);
});

it('non-superadmin cannot manage talking points', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

    $response = $this->actingAs($admin)
        ->get(route('talking-points.index'));

    $response->assertForbidden();
});

it('guest cannot manage talking points', function () {
    $response = $this->get(route('talking-points.index'));
    $response->assertRedirect('/login');
});
