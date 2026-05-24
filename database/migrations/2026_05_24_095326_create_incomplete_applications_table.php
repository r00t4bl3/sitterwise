<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incomplete_applications', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->unsignedTinyInteger('last_step')->default(1);
            $table->json('draft_data')->nullable();
            $table->timestamp('nudged_at')->nullable();
            $table->unsignedTinyInteger('nudge_count')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('last_activity_at');
            $table->timestamps();

            $table->index('last_activity_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomplete_applications');
    }
};
