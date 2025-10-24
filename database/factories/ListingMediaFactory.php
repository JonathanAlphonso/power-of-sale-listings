<?php

namespace Database\Factories;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ListingMedia>
 */
class ListingMediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $position = fake()->numberBetween(0, 12);
        $seed = Str::uuid()->toString();

        $baseUrl = static fn (int $width, int $height) => "https://picsum.photos/seed/{$seed}/{$width}/{$height}";

        return [
            'listing_id' => Listing::factory(),
            'media_type' => 'image',
            'label' => fake()->optional()->sentence(3),
            'position' => $position,
            'is_primary' => $position === 0,
            'url' => $baseUrl(1900, 1260),
            'preview_url' => $baseUrl(900, 600),
            'variants' => [
                '150' => $baseUrl(150, 112),
                '600' => $baseUrl(600, 400),
                '900' => $baseUrl(900, 600),
            ],
            'meta' => [
                'description' => fake()->sentence(),
            ],
        ];
    }
}
