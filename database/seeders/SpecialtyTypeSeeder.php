<?php

namespace Database\Seeders;

use App\Models\SpecialtyType;
use Illuminate\Database\Seeder;

class SpecialtyTypeSeeder extends Seeder
{
    public function run(): void
    {
        $specialties = [
            ['name' => 'Babies', 'description' => 'I am comfortable changing diapers and fixing bottles. I am familiar with safe sleep practices. I can remain calm when a baby is crying and know how to soothe a fussy baby. I wash my hands upon entering the room.', 'sort_order' => 1, 'color_bg' => '#E0F7FA', 'color_text' => '#006064'],
            ['name' => 'Toddlers', 'description' => 'I am aware of dangerous situations — stairs, sharp corners. I am patient with typical toddler meltdowns and can gently take control of a situation. I know how to distract a crying child when parents leave.', 'sort_order' => 2, 'color_bg' => '#E8F5E9', 'color_text' => '#2E7D32'],
            ['name' => 'Preschool', 'description' => 'I love getting on the floor and playing actively with small children. I enjoy reading books and teaching little ones new things. I am okay with an occasional potty training accident.', 'sort_order' => 3, 'color_bg' => '#FFF3E0', 'color_text' => '#E65100'],
            ['name' => 'School Age', 'description' => 'I enjoy older kids and bring plenty of crafts and board games! I know to keep conversation light and avoid controversial topics and questionable media. I like to keep big kids active and engaged.', 'sort_order' => 4, 'color_bg' => '#EDE7F6', 'color_text' => '#4527A0'],
        ];

        foreach ($specialties as $specialty) {
            SpecialtyType::create($specialty);
        }
    }
}
