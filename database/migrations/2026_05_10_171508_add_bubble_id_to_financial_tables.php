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
        Schema::table('client_payments', function (Blueprint $table) {
            $table->string('bubble_id')->nullable()->index();
        });

        Schema::table('caregiver_payouts', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->string('bubble_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('client_payments', function (Blueprint $table) {
            $table->dropColumn('bubble_id');
        });

        Schema::table('caregiver_payouts', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropColumn(['booking_id', 'bubble_id']);
        });
    }
};
