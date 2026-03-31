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
        Schema::create('client_type_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('changed_by_admin_id')->constrained('users')->onDelete('cascade');
            $table->enum('previous_type', ['sd_resident', 'vacationer', 'invoiced']);
            $table->enum('new_type', ['sd_resident', 'vacationer', 'invoiced']);
            $table->text('reason')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_type_changes');
    }
};
