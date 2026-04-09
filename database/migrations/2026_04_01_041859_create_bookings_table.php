<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caregiver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('availability_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('address_id')->nullable()->constrained('client_addresses')->nullOnDelete();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_state')->nullable();
            $table->string('address_zip')->nullable();
            $table->string('service_type');
            $table->string('location_type');
            $table->string('rental_platform')->nullable();
            $table->timestamp('start_datetime');
            $table->timestamp('end_datetime');
            $table->string('status');
            $table->json('special_considerations')->nullable();
            $table->text('caregiver_notes')->nullable();
            $table->text('notes_to_sitterwise')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('corporate_id')->nullable();
            $table->json('sitter_preferences')->nullable();
            $table->string('other_adults_present')->nullable();
            $table->text('special_needs_notes')->nullable();
            $table->text('emergency_instructions')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('reimbursement', 10, 2)->nullable()->default(null);
            $table->decimal('tip', 10, 2)->nullable()->default(null);
            $table->string('payment_status');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->decimal('actual_amount', 10, 2)->nullable();
            $table->integer('charge_attempt_count')->default(0);
            $table->timestamp('last_charge_attempt_at')->nullable();
            $table->boolean('requires_payment')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
