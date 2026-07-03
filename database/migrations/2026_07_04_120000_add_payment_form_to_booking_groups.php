<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_groups', function (Blueprint $table) {
            // Settlement rail for the group, snapshotted from the matched pricing
            // rule (e.g. "Stripe" for card-charged jobs, "OnPay (Payroll)" for
            // invoiced ones). Nullable so existing rows are untouched until the
            // backfill runs; consumers fall back safely when it is null.
            $table->string('payment_form', 50)->nullable()->after('requires_payment');
        });
    }

    public function down(): void
    {
        Schema::table('booking_groups', function (Blueprint $table) {
            $table->dropColumn('payment_form');
        });
    }
};
