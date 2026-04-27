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
        Schema::create('client_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('client_payment_methods')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('usd');
            $table->string('status'); // pending, authorized, captured, failed, refunded
            $table->string('provider'); // stripe, invoice, manual, comped
            $table->string('provider_payment_id')->nullable();
            $table->string('provider_charge_id')->nullable();
            $table->json('metadata')->after('paid_at')->nullable();
            $table->string('error_code')->after('metadata')->nullable();
            $table->text('error_message')->after('error_code')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('client_id');
            $table->index('booking_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_payments');
    }
};
