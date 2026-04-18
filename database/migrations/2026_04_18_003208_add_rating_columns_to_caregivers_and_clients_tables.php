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
        Schema::table('caregivers', function (Blueprint $table) {
            $table->decimal('admin_rating', 3, 2)->nullable()->after('rating');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('rating', 3, 2)->default(0)->after('stripe_customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('caregivers', function (Blueprint $table) {
            $table->dropColumn('admin_rating');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('rating');
        });
    }
};
