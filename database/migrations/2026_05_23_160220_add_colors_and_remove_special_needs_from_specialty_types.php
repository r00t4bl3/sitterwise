<?php

use App\Models\SpecialtyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('specialty_types', function (Blueprint $table) {
            $table->string('color_bg', 7)->nullable()->after('sort_order');
            $table->string('color_text', 7)->nullable()->after('color_bg');
        });

        SpecialtyType::where('name', 'Special Needs')->delete();

        $colors = [
            'Babies' => ['color_bg' => '#E0F7FA', 'color_text' => '#006064'],
            'Toddlers' => ['color_bg' => '#E8F5E9', 'color_text' => '#2E7D32'],
            'Preschool' => ['color_bg' => '#FFF3E0', 'color_text' => '#E65100'],
            'School Age' => ['color_bg' => '#EDE7F6', 'color_text' => '#4527A0'],
        ];

        foreach ($colors as $name => $color) {
            SpecialtyType::where('name', $name)->update($color);
        }
    }

    public function down(): void
    {
        SpecialtyType::whereNull('color_bg')->orWhereNull('color_text')->orWhereNotNull('color_bg');

        Schema::table('specialty_types', function (Blueprint $table) {
            $table->dropColumn(['color_bg', 'color_text']);
        });
    }
};
