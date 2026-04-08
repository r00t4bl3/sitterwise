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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('client_type')->default('vacationer');
            $table->string('corporate_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->enum('how_did_you_hear', ['concierge', 'friend_family', 'google', 'returning_client', 'care_com', 'other'])->nullable();
            $table->json('sitter_preferences')->nullable();
            $table->string('other_adults_present')->nullable();
            $table->text('special_needs_notes')->nullable();
            $table->text('emergency_instructions')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('client_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
