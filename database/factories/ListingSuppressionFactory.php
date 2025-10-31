<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<\App\Models\ListingSuppression>
 */
class ListingSuppressionFactory extends Factory
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
            'user_id' => User::factory(),
            'reason' => $this->faker->sentence(),
            'notes' => $this->faker->optional()->paragraph(),
            'suppressed_at' => now(),
            'expires_at' => $this->faker->optional()->dateTimeBetween('+1 day', '+1 month'),
        ];
    }

    /**
     * Indicate that the suppression has been released.
     *
     * @return $this
     */
    public function released(?Carbon $releasedAt = null): self
    {
        return $this->state(function () use ($releasedAt): array {
            $releasedAt ??= now();

            return [
                'released_at' => $releasedAt,
                'release_user_id' => User::factory(),
                'release_reason' => $this->faker->optional()->sentence(),
                'release_notes' => $this->faker->optional()->paragraph(),
            ];
        });
    }
}
