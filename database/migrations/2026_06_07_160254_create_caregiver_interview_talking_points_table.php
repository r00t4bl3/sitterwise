<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caregiver_interview_talking_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('caregiver_interview_id');
            $table->unsignedBigInteger('talking_point_id')->nullable();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_checked')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('caregiver_interview_id', 'citp_interview_fk')
                ->references('id')->on('caregiver_interviews')->cascadeOnDelete();
            $table->foreign('talking_point_id', 'citp_tp_fk')
                ->references('id')->on('interview_talking_points')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caregiver_interview_talking_points');
    }
};
