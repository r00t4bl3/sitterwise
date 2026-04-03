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
            $table->string('service_type');
            $table->string('location_type');
            $table->timestamp('start_datetime');
            $table->timestamp('end_datetime');
            $table->string('status');
            $table->json('special_considerations')->nullable();
            $table->text('caregiver_notes')->nullable();
            $table->text('notes_to_sitterwise')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('corporate_id')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->string('payment_status');
            $table->boolean('requires_payment')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
