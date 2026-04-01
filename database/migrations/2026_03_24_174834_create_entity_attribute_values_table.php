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
        Schema::create('entity_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id');
            $table->foreignId('attribute_definition_id')->constrained()->onDelete('cascade');
            $table->text('value')->nullable();
            $table->string('entity_type', 50)->default('caregiver');
            $table->timestamps();

            $table->unique(['entity_id', 'attribute_definition_id', 'entity_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_attribute_values');
    }
};