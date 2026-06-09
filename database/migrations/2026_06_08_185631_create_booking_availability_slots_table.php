<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_availability_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caregiver_id')->constrained();
            $table->foreignId('availability_id')->constrained();
            $table->date('date');
            $table->string('time_slot');
            $table->timestamps();

            $table->unique(['booking_id', 'date', 'time_slot'], 'booking_slot_unique');
            $table->index(['availability_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_availability_slots');
    }
};
