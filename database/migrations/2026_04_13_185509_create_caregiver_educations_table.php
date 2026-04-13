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
        Schema::create('caregiver_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caregiver_id')->constrained()->onDelete('cascade');
            $table->enum('education_type', ['high_school', 'college']);
            $table->string('school_name')->nullable();
            $table->integer('graduation_year')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('caregiver_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caregiver_educations');
    }
};
