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
        foreach (['users', 'clients', 'caregivers', 'bookings'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('bubble_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'clients', 'caregivers', 'bookings'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('bubble_id');
            });
        }
    }
};
