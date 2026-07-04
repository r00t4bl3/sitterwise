<?php

use App\Models\QuickLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'role' => 'super_admin',
        'email_verified_at' => now(),
    ]);
});

it('can list quick links', function () {
    QuickLink::factory()->count(3)->create();

    $response = $this->actingAs($this->user)
        ->get(route('quick-links.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('superadmin/quick-links/index')
        ->has('quickLinks', 3)
    );
});

it('caregiver dashboard receives only caregiver-visible quick links', function () {
    $caregiver = User::factory()->create([
        'role' => 'caregiver',
        'email_verified_at' => now(),
    ]);

    QuickLink::factory()->create([
        'title' => 'Caregiver FAQ',
        'is_active' => true,
        'visible_for_roles' => ['admin', 'super_admin', 'caregiver'],
    ]);
    QuickLink::factory()->create([
        'title' => 'Admin Only Tool',
        'is_active' => true,
        'visible_for_roles' => ['admin', 'super_admin'],
    ]);
    QuickLink::factory()->create([
        'title' => 'Inactive Caregiver Link',
        'is_active' => false,
        'visible_for_roles' => ['caregiver'],
    ]);

    $response = $this->actingAs($caregiver)->get(route('dashboard'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('quickLinks', 1)
        ->where('quickLinks.0.title', 'Caregiver FAQ')
    );
});

it('can create a quick link', function () {
    $this->withoutExceptionHandling();
    $data = [
        'title' => 'Google',
        'url' => 'https://google.com',
        'description' => 'Search engine',
        'icon' => 'Link',
        'sort_order' => 1,
        'is_active' => true,
        'is_external' => true,
    ];

    $response = $this->actingAs($this->user)
        ->post(route('quick-links.store'), $data);

    $response->assertRedirect(route('quick-links.index'));
    $this->assertDatabaseHas('quick_links', [
        'title' => 'Google',
        'url' => 'https://google.com',
    ]);
});

it('can update a quick link', function () {
    $quickLink = QuickLink::factory()->create();

    $data = [
        'title' => 'Updated Title',
        'url' => 'https://updated.com',
        'description' => 'Updated description',
        'icon' => 'ExternalLink',
        'sort_order' => 5,
        'is_active' => false,
        'is_external' => false,
    ];

    $response = $this->actingAs($this->user)
        ->patch(route('quick-links.update', $quickLink), $data);

    $response->assertRedirect(route('quick-links.index'));
    $this->assertDatabaseHas('quick_links', [
        'id' => $quickLink->id,
        'title' => 'Updated Title',
        'is_active' => false,
    ]);
});

it('can delete a quick link', function () {
    $quickLink = QuickLink::factory()->create();

    $response = $this->actingAs($this->user)
        ->delete(route('quick-links.destroy', $quickLink));

    $response->assertRedirect(route('quick-links.index'));
    $this->assertDatabaseMissing('quick_links', ['id' => $quickLink->id]);
});

it('can search quick links', function () {
    QuickLink::factory()->create(['title' => 'Searchable Link']);
    QuickLink::factory()->create(['title' => 'Another One']);

    $response = $this->actingAs($this->user)
        ->getJson(route('quick-links.search', ['q' => 'Searchable']));

    $response->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonFragment(['name' => 'Searchable Link']);
});
