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
        Schema::create('client_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('label')->nullable();
            $table->enum('location_type', ['hotel', 'private_home', 'vacation_rental', 'event_venue']);
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('zip');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_addresses');
    }
};
