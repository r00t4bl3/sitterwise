<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_requests', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique()->index();
            $table->foreignId('caregiver_id')->constrained()->cascadeOnDelete();
            $table->string('reference_name');
            $table->string('reference_email');
            $table->string('relationship')->nullable();
            $table->string('years_known')->nullable();
            $table->boolean('is_sponsor')->default(false);
            $table->unsignedTinyInteger('rating_reliability')->nullable();
            $table->unsignedTinyInteger('rating_trustworthiness')->nullable();
            $table->unsignedTinyInteger('rating_maturity')->nullable();
            $table->unsignedTinyInteger('rating_communication')->nullable();
            $table->unsignedTinyInteger('rating_warmth')->nullable();
            $table->unsignedTinyInteger('rating_overall_recommendation')->nullable();
            $table->text('strengths')->nullable();
            $table->text('concerns')->nullable();
            $table->text('additional_comments')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_requests');
    }
};
