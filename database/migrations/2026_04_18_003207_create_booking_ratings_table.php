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
        Schema::create('booking_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rater_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ratee_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('rating', 3, 2);
            $table->text('comment')->nullable();
            $table->string('type', 30); // 'client_to_caregiver' OR 'caregiver_to_client'
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['booking_id', 'rater_id', 'type']);
            $table->index(['ratee_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_ratings');
    }
};
