<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create new table with polymorphic columns
        Schema::create('booking_ratings_new', function (Blueprint $table) {
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

        // Migrate existing data
        DB::statement("
            INSERT INTO booking_ratings_new (id, booking_id, rater_id, ratable_id, ratable_type, rating, comment, created_at, updated_at, deleted_at)
            SELECT id, booking_id, rater_id, ratee_id,
                CASE 
                    WHEN type = 'caregiver_to_client' THEN 'App\\Models\\Client'
                    WHEN type = 'client_to_caregiver' THEN 'App\\Models\\Caregiver'
                END,
                rating, comment, created_at, updated_at, deleted_at
            FROM booking_ratings
        ");

        // Drop old table and rename new one
        Schema::dropIfExists('booking_ratings');
        Schema::rename('booking_ratings_new', 'booking_ratings');
    }

    public function down(): void
    {
        // This is a one-way migration for prototype
    }
};
