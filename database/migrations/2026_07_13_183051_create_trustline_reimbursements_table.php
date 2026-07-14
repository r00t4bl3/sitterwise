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
        Schema::create('trustline_reimbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caregiver_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('jobs_completed');
            $table->integer('reward_amount');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trustline_reimbursements');
    }
};
