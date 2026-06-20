<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reference_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating_appearance')->nullable()->after('rating_overall_recommendation');
            $table->unsignedTinyInteger('rating_punctuality')->nullable()->after('rating_appearance');
            $table->string('background_drug_alcohol', 3)->nullable()->after('additional_comments');
            $table->string('background_tobacco', 3)->nullable()->after('background_drug_alcohol');
            $table->string('trust_own_child', 7)->nullable()->after('background_tobacco');
            $table->string('reason_not_care', 3)->nullable()->after('trust_own_child');
            $table->text('reason_not_care_explanation')->nullable()->after('reason_not_care');
        });
    }

    public function down(): void
    {
        Schema::table('reference_requests', function (Blueprint $table) {
            $table->dropColumn([
                'rating_appearance',
                'rating_punctuality',
                'background_drug_alcohol',
                'background_tobacco',
                'trust_own_child',
                'reason_not_care',
                'reason_not_care_explanation',
            ]);
        });
    }
};
