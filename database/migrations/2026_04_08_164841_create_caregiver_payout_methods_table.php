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
        Schema::create('caregiver_payout_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caregiver_id')->constrained()->onDelete('cascade');
            $table->string('provider');
            $table->string('provider_method_id');
            $table->string('account_type');
            $table->string('bank_name');
            $table->string('last4');
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('caregiver_id');
            $table->unique('provider_method_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caregiver_payout_methods');
    }
};
