<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caregiver_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caregiver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('caregiver_applications')->nullOnDelete();
            $table->json('scores');
            $table->unsignedTinyInteger('composite')->default(0);
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'declined', 'completed'])->default('draft');
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caregiver_interviews');
    }
};
