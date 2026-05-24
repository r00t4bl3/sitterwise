<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reference_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating_reliability')->nullable();
            $table->unsignedTinyInteger('rating_trustworthiness')->nullable();
            $table->unsignedTinyInteger('rating_maturity')->nullable();
            $table->unsignedTinyInteger('rating_communication')->nullable();
            $table->unsignedTinyInteger('rating_warmth')->nullable();
            $table->unsignedTinyInteger('rating_overall_recommendation')->nullable();
            $table->text('strengths')->nullable();
            $table->text('concerns')->nullable();
            $table->text('additional_comments')->nullable();
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('reference_requests', function (Blueprint $table) {
                $table->dropColumn(['rating', 'feedback']);
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('reference_requests', function (Blueprint $table) {
                $table->unsignedTinyInteger('rating')->nullable();
                $table->text('feedback')->nullable();
            });
        }

        Schema::table('reference_requests', function (Blueprint $table) {
            $table->dropColumn([
                'rating_reliability',
                'rating_trustworthiness',
                'rating_maturity',
                'rating_communication',
                'rating_warmth',
                'rating_overall_recommendation',
                'strengths',
                'concerns',
                'additional_comments',
            ]);
        });
    }
};
