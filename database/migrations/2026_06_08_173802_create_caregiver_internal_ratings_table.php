<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caregiver_internal_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('caregiver_id')->unique();
            $table->decimal('communication_score', 3, 2)->nullable();
            $table->text('communication_notes')->nullable();
            $table->timestamp('communication_updated_at')->nullable();
            $table->decimal('reliability_score', 3, 2)->nullable();
            $table->decimal('reliability_override', 3, 2)->nullable();
            $table->timestamp('reliability_cached_at')->nullable();
            $table->decimal('composite_score', 5, 2)->nullable();
            $table->timestamps();

            $table->foreign('caregiver_id', 'cir_caregiver_fk')
                ->references('id')->on('caregivers')->cascadeOnDelete();
        });

        // Seed existing admin_rating values into communication_score
        $rows = DB::table('caregivers')
            ->whereNotNull('admin_rating')
            ->get(['id', 'admin_rating', 'updated_at']);

        if ($rows->isNotEmpty()) {
            DB::table('caregiver_internal_ratings')->upsert(
                $rows->map(fn ($r) => [
                    'caregiver_id' => $r->id,
                    'communication_score' => $r->admin_rating,
                    'communication_updated_at' => $r->updated_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->toArray(),
                'caregiver_id',
                ['communication_score', 'communication_updated_at', 'updated_at'],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('caregiver_internal_ratings');
    }
};
