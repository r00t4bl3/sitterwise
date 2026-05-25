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
        Schema::create('caregiver_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('caregiver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->string('resolution')->nullable()
                ->comment('Values match AssignmentResolution enum: completed, backed_out, backed_out_excused, reassigned, no_show, cancelled_by_sitterwise');
            $table->timestamp('resolution_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->boolean('late_arrival_flag')->default(false);
            $table->text('late_arrival_note')->nullable();
            $table->foreignId('excused_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('excused_at')->nullable();
            $table->timestamps();

            $table->unique(['caregiver_id', 'booking_id'], 'unique_assignment');
            $table->index('resolution');
            $table->index('assigned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caregiver_assignments');
    }
};
