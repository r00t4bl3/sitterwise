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
        Schema::create('client_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('name')->nullable();
            $table->string('gender')->nullable();
            $table->integer('birth_month')->nullable();
            $table->integer('birth_year')->nullable();
            $table->boolean('special_needs')->default(false);
            $table->text('special_needs_notes')->nullable();
            $table->timestamps();

            $table->index('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_children');
    }
};
