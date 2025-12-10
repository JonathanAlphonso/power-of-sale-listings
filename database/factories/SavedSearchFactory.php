<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SavedSearch>
 */
class SavedSearchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->sentence(3);

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name.'-'.fake()->unique()->randomNumber()),
            'notification_channel' => fake()->randomElement(['email', 'none']),
            'notification_frequency' => fake()->randomElement(['instant', 'daily', 'weekly']),
            'is_active' => fake()->boolean(85),
            'last_ran_at' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
            'last_matched_at' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
            'next_run_at' => fake()->optional()->dateTimeBetween('now', '+3 days'),
            'filters' => [
                'min_price' => fake()->optional()->numberBetween(200000, 450000),
                'max_price' => fake()->numberBetween(600000, 1800000),
                'type' => fake()->randomElement(['Condo', 'House', 'Townhouse']),
            ],
            'meta' => null,
        ];
    }
}
