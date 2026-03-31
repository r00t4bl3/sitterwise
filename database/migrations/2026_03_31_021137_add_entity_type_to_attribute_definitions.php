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
        Schema::table('attribute_definitions', function (Blueprint $table) {
            $table->enum('entity_type', ['caregiver', 'client', 'both'])->default('caregiver')->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attribute_definitions', function (Blueprint $table) {
            $table->dropColumn('entity_type');
        });
    }
};
