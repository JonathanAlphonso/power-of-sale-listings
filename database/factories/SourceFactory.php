<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Source>
 */
class SourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();
        $slug = Str::slug($name);

        return [
            'slug' => $slug,
            'name' => $name,
            'type' => fake()->randomElement(['BOARD', 'LENDER', 'SCRAPER']),
            'external_identifier' => strtoupper(fake()->bothify('SRC###')),
            'contact_name' => fake()->optional()->name(),
            'contact_email' => fake()->optional()->companyEmail(),
            'contact_phone' => fake()->optional()->phoneNumber(),
            'website_url' => fake()->optional()->url(),
            'is_active' => true,
            'last_synced_at' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
            'config' => [
                'timezone' => fake()->timezone(),
                'ingest_window' => fake()->randomElement(['hourly', 'daily', 'weekly']),
            ],
            'meta' => [
                'support_ticket' => fake()->optional()->bothify('TCK-#####'),
            ],
        ];
    }
}
