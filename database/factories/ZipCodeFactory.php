<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\ZipCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ZipCode>
 */
class ZipCodeFactory extends Factory
{
    protected $model = ZipCode::class;

    public function definition(): array
    {
        return [
            'zip_code' => (string) $this->faker->unique()->numberBetween(90001, 96162),
            'area' => $this->faker->city(),
            'location_id' => Location::factory(),
        ];
    }
}
