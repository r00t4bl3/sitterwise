<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_links', function (Blueprint $table) {
            $table->json('visible_for_roles')->nullable();
        });

        DB::statement('UPDATE quick_links SET visible_for_roles = \'["admin", "super_admin"]\' WHERE visible_for_roles IS NULL');
    }

    public function down(): void
    {
        Schema::table('quick_links', function (Blueprint $table) {
            $table->dropColumn('visible_for_roles');
        });
    }
};
