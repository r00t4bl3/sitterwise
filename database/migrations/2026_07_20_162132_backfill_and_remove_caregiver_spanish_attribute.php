<?php

use App\Models\Caregiver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The "Spanish" caregiver AttributeDefinition was inert — nothing in matching
     * reads it (the Spanish filter reads caregivers.languages). Migrate anyone
     * marked via that attribute into languages, then remove the redundant
     * attribute so "Spanish" is only set through the Languages control.
     */
    public function up(): void
    {
        $definition = DB::table('attribute_definitions')
            ->where('slug', 'spanish')
            ->where('entity_type', 'caregiver')
            ->first();

        if (! $definition) {
            return;
        }

        $markedCaregiverIds = DB::table('entity_attribute_values')
            ->where('attribute_definition_id', $definition->id)
            ->where('entity_type', 'caregiver')
            ->where('value', 'true')
            ->pluck('entity_id');

        foreach ($markedCaregiverIds as $caregiverId) {
            $caregiver = Caregiver::find($caregiverId);

            if (! $caregiver) {
                continue;
            }

            $languages = $caregiver->languages ?? [];

            if (! in_array('spanish', $languages, true)) {
                $languages[] = 'spanish';
                $caregiver->update(['languages' => $languages]);
            }
        }

        // Remove the inert attribute — scoped strictly to the caregiver "spanish"
        // definition; every other attribute (incl. special_needs) is untouched.
        DB::table('entity_attribute_values')
            ->where('attribute_definition_id', $definition->id)
            ->where('entity_type', 'caregiver')
            ->delete();

        DB::table('attribute_definitions')->where('id', $definition->id)->delete();
    }

    /**
     * Recreate the definition so the schema is restorable. The languages backfill
     * and removed pivot values are a one-way data cleanup and are NOT reversed.
     */
    public function down(): void
    {
        $exists = DB::table('attribute_definitions')
            ->where('slug', 'spanish')
            ->where('entity_type', 'caregiver')
            ->exists();

        if (! $exists) {
            DB::table('attribute_definitions')->insert([
                'name' => 'Spanish',
                'slug' => 'spanish',
                'type' => 'boolean',
                'entity_type' => 'caregiver',
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
