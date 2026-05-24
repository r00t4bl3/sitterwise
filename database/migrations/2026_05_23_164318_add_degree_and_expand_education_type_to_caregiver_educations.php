<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::create('caregiver_educations_new', function (Blueprint $table) {
                $table->id();
                $table->foreignId('caregiver_id')->constrained()->onDelete('cascade');
                $table->string('education_type');
                $table->string('school_name')->nullable();
                $table->integer('graduation_year')->nullable();
                $table->string('degree')->nullable();
                $table->softDeletes();
                $table->timestamps();
                $table->index('caregiver_id');
            });

            DB::statement('INSERT INTO caregiver_educations_new (id, caregiver_id, education_type, school_name, graduation_year, deleted_at, created_at, updated_at) SELECT id, caregiver_id, education_type, school_name, graduation_year, deleted_at, created_at, updated_at FROM caregiver_educations');

            Schema::drop('caregiver_educations');
            Schema::rename('caregiver_educations_new', 'caregiver_educations');
        } else {
            Schema::table('caregiver_educations', function (Blueprint $table) {
                $table->string('degree')->nullable();
            });

            DB::statement('ALTER TABLE caregiver_educations MODIFY education_type VARCHAR(255)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::create('caregiver_educations_old', function (Blueprint $table) {
                $table->id();
                $table->foreignId('caregiver_id')->constrained()->onDelete('cascade');
                $table->string('education_type');
                $table->string('school_name')->nullable();
                $table->integer('graduation_year')->nullable();
                $table->softDeletes();
                $table->timestamps();
                $table->index('caregiver_id');
            });

            DB::statement('INSERT INTO caregiver_educations_old (id, caregiver_id, education_type, school_name, graduation_year, deleted_at, created_at, updated_at) SELECT id, caregiver_id, education_type, school_name, graduation_year, deleted_at, created_at, updated_at FROM caregiver_educations');

            Schema::drop('caregiver_educations');
            Schema::rename('caregiver_educations_old', 'caregiver_educations');
        } else {
            Schema::table('caregiver_educations', function (Blueprint $table) {
                $table->dropColumn('degree');
            });

            DB::statement("ALTER TABLE caregiver_educations MODIFY education_type ENUM('high_school', 'college')");
        }
    }
};
