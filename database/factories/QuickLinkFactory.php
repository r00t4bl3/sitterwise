<?php

namespace Database\Factories;

use App\Models\QuickLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuickLink>
 */
class QuickLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->words(3, true),
            'url' => $this->faker->url(),
            'description' => $this->faker->sentence(),
            'icon' => 'ExternalLink',
            'sort_order' => 0,
            'is_active' => true,
            'is_external' => true,
            'visible_for_roles' => ['admin', 'super_admin'],
        ];
    }
}
