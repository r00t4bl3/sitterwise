<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caregiver_pauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caregiver_id')->constrained()->cascadeOnDelete();
            $table->timestamp('paused_at');
            $table->date('resume_by')->nullable();
            $table->text('pause_reason')->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->timestamps();

            $table->index('caregiver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caregiver_pauses');
    }
};
