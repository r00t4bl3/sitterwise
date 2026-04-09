<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('reimbursement', 10, 2)->nullable()->default(null)->after('total_amount');
            $table->decimal('tip', 10, 2)->nullable()->default(null)->after('reimbursement');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['reimbursement', 'tip']);
        });
    }
};
