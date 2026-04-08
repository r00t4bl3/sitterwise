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
        Schema::create('client_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('provider');
            $table->string('provider_method_id');
            $table->string('brand');
            $table->string('last4');
            $table->integer('exp_month');
            $table->integer('exp_year');
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('client_id');
            $table->unique('provider_method_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_payment_methods');
    }
};
