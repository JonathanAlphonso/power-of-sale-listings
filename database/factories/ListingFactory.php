<?php

namespace Database\Factories;

use App\Models\Municipality;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Listing>
 */
class ListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $boardCode = fake()->randomElement(['TREB', 'OREB', 'RAGBOS', 'ITSO']);
        $mlsNumber = strtoupper(fake()->bothify('??#######'));
        $squareFeet = fake()->numberBetween(650, 3200);
        $squareFeetRangeStart = (int) (floor($squareFeet / 100) * 100);
        $squareFeetRangeEnd = $squareFeetRangeStart + 199;
        $saleType = fake()->randomElement(['SALE', 'RENT']);
        $listPrice = $saleType === 'RENT'
            ? fake()->randomFloat(2, 1800, 6500)
            : fake()->randomFloat(2, 225000, 1750000);
        $originalListPrice = $listPrice + fake()->randomFloat(2, -45000, 55000);
        $originalListPrice = max($originalListPrice, 0.00);
        $priceLow = max($listPrice - fake()->randomFloat(2, 1000, 50000), 0.00);
        $pricePerSquareFoot = $squareFeet > 0 ? round($listPrice / $squareFeet, 2) : null;

        return [
            'source_id' => Source::factory(),
            'municipality_id' => Municipality::factory(),
            'external_id' => "{$boardCode}-{$mlsNumber}",
            'board_code' => $boardCode,
            'mls_number' => $mlsNumber,
            'status_code' => fake()->randomElement(['NEW', 'ACTIVE', 'CONDITIONAL', 'SUSPENDED']),
            'display_status' => fake()->randomElement(['Available', 'Conditionally Sold']),
            'availability' => fake()->randomElement(['A', 'P', 'S']),
            'property_class' => fake()->randomElement(['CONDO', 'RESIDENTIAL', 'COMMERCIAL']),
            'property_type' => fake()->randomElement(['Condo Townhouse', 'Detached', 'Semi-Detached']),
            'property_style' => fake()->randomElement(['2-Storey', 'Bungalow', 'Loft']),
            'sale_type' => $saleType,
            'currency' => 'CAD',
            'street_number' => (string) fake()->buildingNumber(),
            'street_name' => fake()->streetName(),
            'street_address' => fake()->streetAddress(),
            'unit_number' => fake()->optional()->bothify('Suite ###'),
            'city' => fake()->city(),
            'district' => fake()->optional()->citySuffix(),
            'neighbourhood' => fake()->optional()->secondaryAddress(),
            'postal_code' => fake()->postcode(),
            'province' => 'ON',
            'latitude' => fake()->latitude(42.0, 45.5),
            'longitude' => fake()->longitude(-83.0, -78.0),
            'days_on_market' => fake()->numberBetween(0, 120),
            'bedrooms' => fake()->numberBetween(1, 5),
            'bedrooms_possible' => fake()->numberBetween(0, 2),
            'bathrooms' => fake()->randomFloat(1, 1, 5),
            'square_feet' => $squareFeet,
            'square_feet_text' => "{$squareFeetRangeStart}-{$squareFeetRangeEnd}",
            'list_price' => $listPrice,
            'original_list_price' => $originalListPrice,
            'price' => $listPrice,
            'price_low' => $priceLow,
            'price_per_square_foot' => $pricePerSquareFoot,
            'price_change' => fake()->numberBetween(-5, 5),
            'price_change_direction' => fake()->randomElement([-1, 0, 1]),
            'is_address_public' => fake()->boolean(90),
            'parcel_id' => fake()->optional()->bothify('########-####'),
            'modified_at' => fake()->dateTimeBetween('-2 months', 'now'),
            'ingestion_batch_id' => 'batch-'.Str::slug(fake()->uuid()),
            'payload' => [
                'gid' => $boardCode,
                'source' => 'factory',
                'notes' => fake()->sentence(),
            ],
        ];
    }
}
