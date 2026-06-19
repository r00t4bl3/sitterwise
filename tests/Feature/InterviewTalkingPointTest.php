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

it('can create a talking point and returns JSON', function () {
    $data = [
        'label' => 'Discuss weekend availability',
        'description' => 'Ask if they can work Saturdays and Sundays',
    ];

    $response = $this->actingAs($this->user)
        ->post(route('talking-points.store'), $data, ['Accept' => 'application/json']);

    $response->assertSuccessful();
    $response->assertJson(['label' => 'Discuss weekend availability']);
    $this->assertDatabaseHas('interview_talking_points', [
        'label' => 'Discuss weekend availability',
        'description' => 'Ask if they can work Saturdays and Sundays',
    ]);
});

it('can update a talking point and returns JSON', function () {
    $point = InterviewTalkingPoint::create(['label' => 'Old label', 'sort_order' => 0]);

    $data = [
        'label' => 'Updated label',
        'description' => 'Updated description',
    ];

    $response = $this->actingAs($this->user)
        ->put(route('talking-points.update', $point), $data, ['Accept' => 'application/json']);

    $response->assertSuccessful();
    $response->assertJson(['label' => 'Updated label']);
    $this->assertDatabaseHas('interview_talking_points', [
        'id' => $point->id,
        'label' => 'Updated label',
        'description' => 'Updated description',
    ]);
});

it('can delete a talking point and returns JSON', function () {
    $point = InterviewTalkingPoint::create(['label' => 'To delete', 'sort_order' => 0]);

    $response = $this->actingAs($this->user)
        ->delete(route('talking-points.destroy', $point), [], ['Accept' => 'application/json']);

    $response->assertSuccessful();
    $response->assertJson(['deleted' => true]);
    $this->assertDatabaseMissing('interview_talking_points', ['id' => $point->id]);
});

it('can reorder talking points', function () {
    $point1 = InterviewTalkingPoint::create(['label' => 'First', 'sort_order' => 0]);
    $point2 = InterviewTalkingPoint::create(['label' => 'Second', 'sort_order' => 1]);
    $point3 = InterviewTalkingPoint::create(['label' => 'Third', 'sort_order' => 2]);

    $response = $this->actingAs($this->user)
        ->post(route('talking-points.reorder'), [
            'ids' => [$point3->id, $point1->id, $point2->id],
        ], ['Accept' => 'application/json']);

    $response->assertSuccessful();
    $response->assertJson(['reordered' => true]);

    $this->assertDatabaseHas('interview_talking_points', ['id' => $point3->id, 'sort_order' => 0]);
    $this->assertDatabaseHas('interview_talking_points', ['id' => $point1->id, 'sort_order' => 1]);
    $this->assertDatabaseHas('interview_talking_points', ['id' => $point2->id, 'sort_order' => 2]);
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
