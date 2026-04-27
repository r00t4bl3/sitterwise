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
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('total_service_amount', 10, 2)->after('paid_to_caregiver_total')->nullable();
        });

        Schema::table('client_payments', function (Blueprint $table) {
            $table->json('metadata')->after('paid_at')->nullable();
            $table->string('error_code')->after('metadata')->nullable();
            $table->text('error_message')->after('error_code')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('total_service_amount');
        });

        Schema::table('client_payments', function (Blueprint $table) {
            $table->dropColumn(['metadata', 'error_code', 'error_message']);
        });
    }
};
