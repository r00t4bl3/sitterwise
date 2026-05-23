<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE caregivers SET status = LOWER(REPLACE(status, ' ', '_')) WHERE status IS NOT NULL");
    }

    public function down(): void
    {
        // Irreversible — we don't know the original case values
    }
};
