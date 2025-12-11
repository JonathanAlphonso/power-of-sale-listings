<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Services\GeocodingService;
use Illuminate\Console\Command;

use function Laravel\Prompts\progress;

class GeocodeListings extends Command
{
    protected $signature = 'listings:geocode
                            {--limit= : Maximum number of listings to geocode}
                            {--force : Re-geocode listings that already have coordinates}';

    protected $description = 'Geocode listings that are missing coordinates';

    public function handle(GeocodingService $geocoder): int
    {
        $query = Listing::query()
            ->whereNotNull('street_address')
            ->whereNotNull('city');

        if (! $this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('latitude')
                    ->orWhereNull('longitude');
            });
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $listings = $query->get();

        if ($listings->isEmpty()) {
            $this->info('No listings need geocoding.');

            return self::SUCCESS;
        }

        $this->info("Found {$listings->count()} listings to geocode.");

        $geocoded = 0;
        $failed = 0;

        $progress = progress(
            label: 'Geocoding listings',
            steps: $listings->count(),
        );

        $progress->start();

        foreach ($listings as $listing) {
            // street_address often contains full address already (e.g., "123 Main St, Toronto, ON M5V 1A1")
            // If it looks complete, use it directly; otherwise build from parts
            $address = $this->buildGeocodableAddress($listing, $geocoder);

            if ($address === null) {
                $failed++;
                $progress->advance();

                continue;
            }

            $coordinates = $geocoder->geocode($address);

            if ($coordinates !== null) {
                $listing->update([
                    'latitude' => $coordinates['latitude'],
                    'longitude' => $coordinates['longitude'],
                ]);
                $geocoded++;
            } else {
                $failed++;
            }

            $progress->advance();

            // Nominatim rate limit: 1 request per second
            usleep(1100000);
        }

        $progress->finish();

        $this->newLine();
        $this->info("Geocoded: {$geocoded}");
        $this->info("Failed: {$failed}");

        return self::SUCCESS;
    }

    /**
     * Build a geocodable address from listing data.
     */
    private function buildGeocodableAddress(Listing $listing, GeocodingService $geocoder): ?string
    {
        // If street_address contains postal code, it's likely a full address
        if ($listing->street_address && $listing->postal_code
            && str_contains($listing->street_address, $listing->postal_code)) {
            // Clean up any board codes in the address (e.g., "Toronto C08" -> "Toronto")
            return $this->cleanBoardCodes($listing->street_address);
        }

        // Otherwise build from parts
        return $geocoder->buildAddress(
            $listing->street_address,
            $this->cleanBoardCodes($listing->city),
            $listing->province ?? 'ON',
            $listing->postal_code,
        );
    }

    /**
     * Remove board area codes (e.g., "Toronto C08" -> "Toronto")
     * Board codes are single letter + 2 digits like C08, W02, E01
     * Must not match postal codes (letter-digit-letter digit-letter-digit)
     */
    private function cleanBoardCodes(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Match board codes: space + single letter + exactly 2 digits (not followed by more alphanumerics)
        // This avoids matching postal codes like "L1K 2L9"
        return preg_replace('/ [A-Z]\d{2}(?![A-Z0-9])/i', '', $value);
    }
}
