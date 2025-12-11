<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';

    private const USER_AGENT = 'PowerOfSalesOntario/1.0';

    /**
     * Geocode an address and return coordinates.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function geocode(string $address): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
            ])->get(self::NOMINATIM_URL, [
                'q' => $address,
                'format' => 'json',
                'limit' => 1,
                'countrycodes' => 'ca',
            ]);

            if (! $response->successful()) {
                Log::warning('Geocoding request failed', [
                    'address' => $address,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $results = $response->json();

            if (empty($results)) {
                Log::debug('No geocoding results found', ['address' => $address]);

                return null;
            }

            $result = $results[0];

            return [
                'latitude' => (float) $result['lat'],
                'longitude' => (float) $result['lon'],
            ];
        } catch (\Exception $e) {
            Log::error('Geocoding error', [
                'address' => $address,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build a geocodable address string from listing attributes.
     */
    public function buildAddress(
        ?string $streetAddress,
        ?string $city,
        ?string $province,
        ?string $postalCode
    ): ?string {
        $parts = array_filter([
            $streetAddress,
            $city,
            $province,
            $postalCode,
        ]);

        if (empty($parts)) {
            return null;
        }

        return implode(', ', $parts);
    }
}
