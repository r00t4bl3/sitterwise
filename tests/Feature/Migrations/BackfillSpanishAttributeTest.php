<?php

use App\Models\Caregiver;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The real migration already ran (and removed the attribute) under RefreshDatabase,
 * so we recreate the inert "spanish" attribute scenario and invoke the migration's
 * up() directly to prove the backfill + removal behaviour.
 */
function runSpanishBackfillMigration(): void
{
    $migration = require base_path(
        'database/migrations/2026_07_20_162132_backfill_and_remove_caregiver_spanish_attribute.php'
    );

    $migration->up();
}

/**
 * The factory's afterCreating hook randomly attaches caregiver attributes; clear
 * them so each test controls exactly which attributes a caregiver carries.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeCleanCaregiver(array $attributes = []): Caregiver
{
    $caregiver = Caregiver::factory()->create($attributes);
    $caregiver->attributes()->sync([]);

    return $caregiver;
}

function markAttribute(int $definitionId, int $caregiverId): void
{
    DB::table('entity_attribute_values')->insert([
        'attribute_definition_id' => $definitionId,
        'entity_type' => 'caregiver',
        'entity_id' => $caregiverId,
        'value' => 'true',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function () {
    $this->seed([
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        CertificationTypeSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);

    // Recreate the inert "spanish" attribute the seeder no longer defines.
    $this->spanishDefinitionId = DB::table('attribute_definitions')->insertGetId([
        'name' => 'Spanish',
        'slug' => 'spanish',
        'type' => 'boolean',
        'entity_type' => 'caregiver',
        'sort_order' => 2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->specialNeedsDefinitionId = DB::table('attribute_definitions')
        ->where('slug', 'special_needs')
        ->where('entity_type', 'caregiver')
        ->value('id');
});

test('a marked caregiver with no languages gets spanish backfilled', function () {
    $caregiver = makeCleanCaregiver(['languages' => null]);
    markAttribute($this->spanishDefinitionId, $caregiver->id);

    runSpanishBackfillMigration();

    expect($caregiver->fresh()->languages)->toBe(['spanish']);
});

test('a marked caregiver already speaking spanish is not duplicated', function () {
    $caregiver = makeCleanCaregiver(['languages' => ['spanish', 'french']]);
    markAttribute($this->spanishDefinitionId, $caregiver->id);

    runSpanishBackfillMigration();

    expect($caregiver->fresh()->languages)->toBe(['spanish', 'french']);
});

test('an unmarked caregiver is left untouched', function () {
    $caregiver = makeCleanCaregiver(['languages' => ['french']]);

    runSpanishBackfillMigration();

    expect($caregiver->fresh()->languages)->toBe(['french']);
});

test('the spanish attribute definition and its pivot rows are removed', function () {
    $caregiver = makeCleanCaregiver(['languages' => null]);
    markAttribute($this->spanishDefinitionId, $caregiver->id);

    runSpanishBackfillMigration();

    expect(DB::table('attribute_definitions')
        ->where('slug', 'spanish')
        ->where('entity_type', 'caregiver')
        ->exists())->toBeFalse();

    expect(DB::table('entity_attribute_values')
        ->where('attribute_definition_id', $this->spanishDefinitionId)
        ->exists())->toBeFalse();
});

test('the special_needs attribute is not affected', function () {
    $caregiver = makeCleanCaregiver(['languages' => null]);
    markAttribute($this->specialNeedsDefinitionId, $caregiver->id);

    runSpanishBackfillMigration();

    expect(DB::table('attribute_definitions')
        ->where('slug', 'special_needs')
        ->exists())->toBeTrue();

    expect(DB::table('entity_attribute_values')
        ->where('attribute_definition_id', $this->specialNeedsDefinitionId)
        ->where('entity_id', $caregiver->id)
        ->exists())->toBeTrue();

    expect($caregiver->fresh()->languages)->toBeNull();
});
