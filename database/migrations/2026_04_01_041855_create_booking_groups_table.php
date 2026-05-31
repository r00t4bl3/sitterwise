<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->timestamp('submitted_at');
            $table->string('submission_type');
            $table->string('service_type');
            $table->string('location_type');
            $table->string('rental_platform')->nullable();
            $table->string('client_first_name');
            $table->string('client_last_name');
            $table->string('client_phone')->nullable();
            $table->string('client_email');
            $table->foreignId('address_id')->nullable()->constrained('client_addresses')->nullOnDelete();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_state')->nullable();
            $table->string('address_zip')->nullable();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hotel_name', 255)->nullable();
            $table->json('children')->nullable();
            $table->json('pets')->nullable();
            $table->text('children_notes')->nullable();
            $table->json('sitter_preferences')->nullable();
            $table->string('other_adults_present')->nullable();
            $table->text('special_needs_notes')->nullable();
            $table->text('emergency_instructions')->nullable();
            $table->string('how_did_you_hear')->nullable();
            $table->text('caregiver_notes')->nullable();
            $table->text('notes_to_sitterwise')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('corporate_id')->nullable();
            $table->boolean('requires_payment')->default(true);
            $table->json('special_considerations')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_groups');
    }
};
