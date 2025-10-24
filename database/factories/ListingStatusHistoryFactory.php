<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ListingStatusHistory>
 */
class ListingStatusHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'source_id' => Source::factory(),
            'status_code' => fake()->randomElement(['NEW', 'ACTIVE', 'SUSPENDED', 'RENTED']),
            'status_label' => fake()->randomElement(['Available', 'Sold Conditional', 'Leased']),
            'notes' => fake()->optional()->sentence(),
            'changed_at' => fake()->dateTimeBetween('-2 months', 'now'),
            'payload' => [
                'context' => 'factory',
                'reason' => fake()->optional()->sentence(),
            ],
        ];
    }
}
