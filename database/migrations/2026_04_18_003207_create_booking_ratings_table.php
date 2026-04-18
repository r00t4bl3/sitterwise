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
            $table->unsignedBigInteger('booking_id');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->unsignedBigInteger('rater_id');
            $table->foreign('rater_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('ratable_id');
            $table->string('ratable_type', 255);
            $table->decimal('rating', 3, 2);
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['booking_id', 'rater_id', 'ratable_id', 'ratable_type'], 'rating_unique');
            $table->index(['ratable_id', 'ratable_type']);
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
