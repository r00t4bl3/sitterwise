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
        Schema::create('caregiver_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caregiver_id')->constrained()->onDelete('cascade');
            $table->foreignId('caregiver_payout_method_id')->constrained('caregiver_payout_methods')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->string('currency')->default('usd');
            $table->string('status')->default('pending'); // pending, paid, failed
            $table->string('provider_transfer_id')->unique()->nullable();
            $table->datetime('payout_date')->nullable();
            $table->timestamps();

            $table->index('caregiver_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caregiver_payouts');
    }
};
