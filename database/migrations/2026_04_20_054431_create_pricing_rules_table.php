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
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('service_type');
            $table->integer('number_of_children')->nullable();
            $table->boolean('is_for_pets')->default(false);
            $table->decimal('charge_to_client', 8, 2);
            $table->text('charge_to_client_notes')->nullable();
            $table->decimal('paid_to_caregiver', 8, 2);
            $table->string('payment_form', 50);
            $table->decimal('sitterwise_cut', 8, 2);
            $table->timestamps();

            $table->unique(['service_type', 'number_of_children', 'is_for_pets'], 'unique_pricing_rule');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
