<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite doesn't support rename column, so we need to recreate the table
        Schema::create('entity_attribute_values_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id');
            $table->foreignId('attribute_definition_id')->constrained()->onDelete('cascade');
            $table->text('value')->nullable();
            $table->enum('entity_type', ['caregiver', 'client'])->default('caregiver');
            $table->timestamps();
            $table->unique(['entity_id', 'attribute_definition_id', 'entity_type']);
        });

        // Copy data from old table
        DB::statement('INSERT INTO entity_attribute_values_new (id, entity_id, attribute_definition_id, value, entity_type, created_at, updated_at) 
            SELECT id, caregiver_id, attribute_definition_id, value, entity_type, created_at, updated_at 
            FROM entity_attribute_values');

        Schema::drop('entity_attribute_values');
        Schema::rename('entity_attribute_values_new', 'entity_attribute_values');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('entity_attribute_values_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caregiver_id')->constrained()->onDelete('cascade');
            $table->foreignId('attribute_definition_id')->constrained()->onDelete('cascade');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['caregiver_id', 'attribute_definition_id']);
        });

        DB::statement('INSERT INTO entity_attribute_values_old (id, caregiver_id, attribute_definition_id, value, created_at, updated_at) 
            SELECT id, entity_id, attribute_definition_id, value, created_at, updated_at 
            FROM entity_attribute_values WHERE entity_type = ?', ['caregiver']);

        Schema::drop('entity_attribute_values');
        Schema::rename('entity_attribute_values_old', 'entity_attribute_values');
    }
};
