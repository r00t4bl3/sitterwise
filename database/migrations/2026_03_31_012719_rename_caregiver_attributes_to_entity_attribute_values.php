<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('caregiver_attributes', 'entity_attribute_values');

        Schema::table('entity_attribute_values', function (Blueprint $table) {
            $table->enum('entity_type', ['caregiver', 'client'])->default('caregiver')->after('caregiver_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_attribute_values', function (Blueprint $table) {
            $table->dropColumn('entity_type');
        });

        Schema::rename('entity_attribute_values', 'caregiver_attributes');
    }
};
