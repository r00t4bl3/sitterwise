<?php

use App\Models\Caregiver;
use App\Models\Location;
use App\Models\SpecialtyType;
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

describe('Caregiver availability preferences (self-service)', function () {
    test('a caregiver can view the preferences page with options', function () {
        $this->actingAs($this->caregiver->user)
            ->get(route('settings.caregiver.availability'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/availability-preferences')
                ->has('specialtyTypes', SpecialtyType::active()->count())
                ->has('locations', Location::active()->count())
            );
    });

    test('a caregiver can save age groups and a preferred area', function () {
        $babies = SpecialtyType::where('name', 'Babies')->first();
        $toddlers = SpecialtyType::where('name', 'Toddlers')->first();
        $north = Location::where('name', 'North County')->first();
        $south = Location::where('name', 'South County')->first();

        $this->actingAs($this->caregiver->user)
            ->put(route('settings.caregiver.availability.update'), [
                'specialty_type_ids' => [$babies->id, $toddlers->id],
                'location_ids' => [$north->id, $south->id],
                'preferred_location_id' => $north->id,
            ])
            ->assertRedirect(route('settings.caregiver.availability'))
            ->assertSessionHas('success');

        $this->caregiver->refresh();

        expect($this->caregiver->specialtyTypes->pluck('id')->sort()->values()->all())
            ->toBe(collect([$babies->id, $toddlers->id])->sort()->values()->all());

        // North is preferred, South is willing.
        expect($this->caregiver->locations()->wherePivot('is_preferred', true)->pluck('locations.id')->all())
            ->toBe([$north->id]);
        expect($this->caregiver->locations()->wherePivot('is_preferred', false)->pluck('locations.id')->all())
            ->toBe([$south->id]);
    });

    test('saving replaces the previous selections (sync)', function () {
        $babies = SpecialtyType::where('name', 'Babies')->first();
        $this->caregiver->specialtyTypes()->sync([$babies->id]);

        $schoolAge = SpecialtyType::where('name', 'School Age')->first();

        $this->actingAs($this->caregiver->user)
            ->put(route('settings.caregiver.availability.update'), [
                'specialty_type_ids' => [$schoolAge->id],
            ]);

        expect($this->caregiver->fresh()->specialtyTypes->pluck('id')->all())
            ->toBe([$schoolAge->id]);
    });

    test('a caregiver can save the languages they speak', function () {
        $this->actingAs($this->caregiver->user)
            ->put(route('settings.caregiver.availability.update'), [
                'languages' => ['spanish', 'french'],
            ])
            ->assertRedirect(route('settings.caregiver.availability'))
            ->assertSessionHas('success');

        expect($this->caregiver->fresh()->languages)->toBe(['spanish', 'french']);
    });

    test('saving replaces the previously selected languages', function () {
        $this->caregiver->update(['languages' => ['spanish', 'german']]);

        $this->actingAs($this->caregiver->user)
            ->put(route('settings.caregiver.availability.update'), [
                'languages' => ['french'],
            ]);

        expect($this->caregiver->fresh()->languages)->toBe(['french']);
    });

    test('an invalid language value is rejected', function () {
        $this->actingAs($this->caregiver->user)
            ->put(route('settings.caregiver.availability.update'), [
                'languages' => ['klingon'],
            ])
            ->assertSessionHasErrors('languages.0');
    });

    test('the preferences page exposes language options and selections', function () {
        $this->caregiver->update(['languages' => ['spanish']]);

        $this->actingAs($this->caregiver->user)
            ->get(route('settings.caregiver.availability'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('languageOptions', 11)
                ->where('selectedLanguages', ['spanish'])
            );
    });

    test('non-caregivers are redirected away', function (string $role) {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get(route('settings.caregiver.availability'))
            ->assertRedirect(route('profile.edit'));
    })->with(['admin', 'client']);

    test('non-caregivers cannot update languages', function () {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->put(route('settings.caregiver.availability.update'), [
                'languages' => ['spanish'],
            ])
            ->assertRedirect(route('profile.edit'));
    });
});
