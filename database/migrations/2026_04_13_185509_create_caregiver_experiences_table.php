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
        Schema::create('caregiver_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caregiver_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('sequence')->unsigned()->default(1);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('details')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('caregiver_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caregiver_experiences');
    }
};
