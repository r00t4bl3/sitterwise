<?php

use App\Models\Caregiver;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $this->caregiver = Caregiver::factory()->create();
});

describe('Caregiver languages (self-service)', function () {
    test('the page exposes language options and current selections', function () {
        $this->caregiver->update(['languages' => ['spanish']]);

        $this->actingAs($this->caregiver->user)
            ->get(route('settings.caregiver.languages'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/languages')
                ->has('languageOptions', 11)
                ->where('selectedLanguages', ['spanish'])
            );
    });

    test('a caregiver can save the languages they speak', function () {
        $this->actingAs($this->caregiver->user)
            ->put(route('settings.caregiver.languages.update'), [
                'languages' => ['spanish', 'french'],
            ])
            ->assertRedirect(route('settings.caregiver.languages'))
            ->assertSessionHas('success');

        expect($this->caregiver->fresh()->languages)->toBe(['spanish', 'french']);
    });

    test('saving replaces the previously selected languages', function () {
        $this->caregiver->update(['languages' => ['spanish', 'german']]);

        $this->actingAs($this->caregiver->user)
            ->put(route('settings.caregiver.languages.update'), [
                'languages' => ['french'],
            ]);

        expect($this->caregiver->fresh()->languages)->toBe(['french']);
    });

    test('clearing all languages saves an empty list', function () {
        $this->caregiver->update(['languages' => ['spanish']]);

        $this->actingAs($this->caregiver->user)
            ->put(route('settings.caregiver.languages.update'), []);

        expect($this->caregiver->fresh()->languages)->toBe([]);
    });

    test('an invalid language value is rejected', function () {
        $this->actingAs($this->caregiver->user)
            ->put(route('settings.caregiver.languages.update'), [
                'languages' => ['klingon'],
            ])
            ->assertSessionHasErrors('languages.0');
    });

    test('non-caregivers are redirected away from the page', function (string $role) {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get(route('settings.caregiver.languages'))
            ->assertRedirect(route('profile.edit'));
    })->with(['admin', 'client']);

    test('non-caregivers cannot update languages', function () {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->put(route('settings.caregiver.languages.update'), [
                'languages' => ['spanish'],
            ])
            ->assertRedirect(route('profile.edit'));
    });
});
