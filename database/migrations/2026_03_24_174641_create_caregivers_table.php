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
        Schema::create('caregivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('status_id')->constrained('caregiver_statuses');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('slug')->unique();
            $table->string('phone')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_state')->nullable();
            $table->string('address_zip')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->text('biography')->nullable();
            $table->text('notes')->nullable();
            $table->string('stripe_account_id')->nullable();
            $table->boolean('stripe_charges_enabled')->nullable();
            $table->string('education_level')->nullable();
            $table->json('languages')->nullable();
            $table->json('metadata')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('first_name');
            $table->index('last_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caregivers');
    }
};
