<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Municipality>
 */
class MunicipalityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->unique()->city();
        $province = 'ON';

        return [
            'slug' => Str::slug("{$province}-{$city}"),
            'name' => $city,
            'province' => $province,
            'region' => fake()->optional()->citySuffix(),
            'district' => fake()->optional()->citySuffix(),
            'latitude' => fake()->latitude(42.0, 45.5),
            'longitude' => fake()->longitude(-83.0, -78.0),
            'meta' => [
                'data_source' => fake()->randomElement(['statistics-canada', 'mls']),
            ],
        ];
    }
}
