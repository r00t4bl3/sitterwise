<?php

use App\Enums\CaregiverStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('caregivers', 'status')) {
            Schema::table('caregivers', function (Blueprint $table) {
                $table->string('status')->nullable()->after('user_id');
            });
        }

        if (Schema::hasTable('caregiver_statuses') && Schema::hasColumn('caregivers', 'status_id')) {
            DB::statement('UPDATE caregivers SET status = (SELECT name FROM caregiver_statuses WHERE caregiver_statuses.id = caregivers.status_id) WHERE EXISTS (SELECT 1 FROM caregiver_statuses WHERE caregiver_statuses.id = caregivers.status_id)');

            Schema::table('caregivers', function (Blueprint $table) {
                $table->dropForeign(['status_id']);
                $table->dropColumn('status_id');
            });

            Schema::dropIfExists('caregiver_statuses');
        }

        if (Schema::hasTable('caregiver_statuses')) {
            Schema::dropIfExists('caregiver_statuses');
        }
    }

    public function down(): void
    {
        Schema::create('caregiver_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->string('color', 7)->default('#6B7280');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        foreach (CaregiverStatus::cases() as $i => $case) {
            DB::table('caregiver_statuses')->insert([
                'name' => $case->value,
                'color' => $case->color(),
                'is_active' => true,
                'sort_order' => $i,
            ]);
        }

        if (! Schema::hasColumn('caregivers', 'status_id')) {
            Schema::table('caregivers', function (Blueprint $table) {
                $table->unsignedBigInteger('status_id')->nullable()->after('user_id');
            });
        }

        DB::statement('UPDATE caregivers SET status_id = (SELECT id FROM caregiver_statuses WHERE caregiver_statuses.name = caregivers.status) WHERE EXISTS (SELECT 1 FROM caregiver_statuses WHERE caregiver_statuses.name = caregivers.status)');

        Schema::table('caregivers', function (Blueprint $table) {
            $table->foreign('status_id')->references('id')->on('caregiver_statuses');
        });

        if (Schema::hasColumn('caregivers', 'status')) {
            Schema::table('caregivers', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
