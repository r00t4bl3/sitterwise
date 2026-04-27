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
            $table->ulid('ulid')->unique();
            $table->foreignId('booking_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caregiver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('availability_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('address_id')->nullable()->constrained('client_addresses')->nullOnDelete();
            $table->foreignId('pricing_rule_id')->nullable()->nullOnDelete();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_state')->nullable();
            $table->string('address_zip')->nullable();
            $table->string('client_first_name')->nullable();
            $table->string('client_last_name')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('client_email')->nullable();
            $table->json('children')->nullable();
            $table->json('pets')->nullable();
            $table->string('service_type');
            $table->string('location_type');
            $table->string('rental_platform')->nullable();
            $table->timestamp('start_datetime');
            $table->timestamp('end_datetime');
            $table->timestamp('checkout_at')->nullable();
            $table->decimal('total_working_hour', 5, 2)->nullable();
            $table->string('status');
            $table->unsignedBigInteger('reserved_by')->nullable();
            $table->timestamp('reservation_expires_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->json('special_considerations')->nullable();
            $table->text('caregiver_notes')->nullable();
            $table->text('notes_to_sitterwise')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('corporate_id')->nullable();
            $table->json('sitter_preferences')->nullable();
            $table->string('other_adults_present')->nullable();
            $table->text('special_needs_notes')->nullable();
            $table->text('emergency_instructions')->nullable();
            $table->string('how_did_you_hear')->nullable();
            $table->decimal('charge_to_client_hourly', 10, 2)->nullable();
            $table->decimal('paid_to_caregiver_hourly', 10, 2)->nullable();
            $table->decimal('sitterwise_cut_hourly', 10, 2)->nullable();
            $table->decimal('charge_to_client', 10, 2)->nullable()->comment('Total charge to client for the booking: charge_to_client_hourly * total_working_hour');
            $table->decimal('paid_to_caregiver', 10, 2)->nullable()->comment('Total amount paid to caregiver for the booking: paid_to_caregiver_hourly * total_working_hour');
            $table->decimal('sitterwise_cut', 10, 2)->nullable()->comment('Total amount cut by Sitterwise for the booking: sitterwise_cut_hourly * total_working_hour');
            $table->decimal('reimbursement', 10, 2)->nullable()->default(null)->comment('Total reimbursement amount for the booking');
            $table->string('reimbursement_description')->nullable();
            $table->decimal('bonus', 10, 2)->nullable()->default(null);
            $table->decimal('tip', 10, 2)->nullable()->default(null);
            $table->decimal('paid_to_caregiver_total', 10, 2)->nullable()->comment('Total amount paid to caregiver for the booking: paid_to_caregiver + reimbursement + bonus + tip');
            $table->decimal('total_service_amount', 10, 2)->after('paid_to_caregiver_total')->nullable()->comment('Total amount for the booking: charge_to_client + reimbursement + bonus');
            $table->decimal('total_amount', 10, 2)->comment('Total amount for the booking: total_service_amount + tip');
            $table->string('payment_status');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->decimal('actual_amount', 10, 2)->nullable();
            $table->integer('charge_attempt_count')->default(0);
            $table->timestamp('last_charge_attempt_at')->nullable();
            $table->boolean('requires_payment')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->comment('Bookings table to store all booking information, which is true at the time of booking. This allows us to keep a historical record of bookings even if related data changes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
