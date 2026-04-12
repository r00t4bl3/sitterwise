<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_caregiver_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caregiver_id')->constrained()->cascadeOnDelete();
            $table->timestamp('notified_at')->useCurrent();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->boolean('claimed')->default(false);
            $table->timestamps();

            // Prevent duplicate notifications
            $table->unique(['booking_id', 'caregiver_id']);
            // Index for finding open/available caregivers quickly
            $table->index(['booking_id', 'claimed', 'responded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_caregiver_notifications');
    }
};
