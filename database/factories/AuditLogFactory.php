<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'auditable_type' => Listing::class,
            'auditable_id' => Listing::factory(),
            'user_id' => User::factory(),
            'action' => fake()->randomElement(['created', 'updated', 'deleted']),
            'old_values' => [
                'status_code' => 'NEW',
            ],
            'new_values' => [
                'status_code' => 'ACTIVE',
            ],
            'meta' => [
                'ip_geo' => fake()->countryCode(),
            ],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'occurred_at' => fake()->dateTimeBetween('-1 day', 'now'),
        ];
    }
}
