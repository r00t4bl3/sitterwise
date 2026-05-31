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
            $table->foreignId('caregiver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('availability_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pricing_rule_id')->nullable()->nullOnDelete();
            $table->timestamp('start_datetime');
            $table->timestamp('end_datetime');
            $table->timestamp('checkout_at')->nullable();
            $table->decimal('total_working_hour', 5, 2)->nullable();
            $table->string('status');
            $table->unsignedBigInteger('reserved_by')->nullable();
            $table->timestamp('reservation_expires_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
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
            $table->decimal('hotel_fee', 10, 2)->nullable()->default(null);
            $table->decimal('paid_to_caregiver_total', 10, 2)->nullable()->comment('Total amount paid to caregiver for the booking: paid_to_caregiver + reimbursement + bonus + tip');
            $table->decimal('total_service_amount', 10, 2)->nullable()->comment('Total amount for the booking: charge_to_client + reimbursement + bonus');
            $table->decimal('total_amount', 10, 2)->comment('Total amount for the booking: total_service_amount + tip');
            $table->string('payment_status');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->decimal('actual_amount', 10, 2)->nullable()->comment('Actual amount charged to client: total_service_amount * 100');
            $table->integer('charge_attempt_count')->default(0);
            $table->timestamp('last_charge_attempt_at')->nullable();
            $table->string('bubble_id')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->onDelete('set null');
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
