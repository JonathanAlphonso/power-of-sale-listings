<?php

declare(strict_types=1);

namespace App\Services\Idx;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class IdxClient
{
    public function __construct(private Repository $config) {}

    public function isEnabled(): bool
    {
        return filled($this->baseUri()) && filled($this->token());
    }

    /**
     * @return array<int, array{
     *     listing_key: string|null,
     *     address: string|null,
     *     city: string|null,
     *     state: string|null,
     *     postal_code: string|null,
     *     list_price: float|int|string|null,
     *     status: string|null,
     *     property_type: string|null,
     *     property_sub_type: string|null,
     *     list_office_name: string|null,
     *     remarks: string|null,
     *     modified_at: CarbonImmutable|null,
     *     virtual_tour_url: string|null,
     * }>
     */
    public function fetchListings(int $limit = 4): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $cacheKey = sprintf('idx.listings.%d', $limit);

        if (Cache::has($cacheKey)) {
            /** @var array<int, array<string, mixed>> $cached */
            $cached = Cache::get($cacheKey, []);

            return $cached;
        }

        $listings = $this->retrieveListings($limit);

        if ($listings !== []) {
            Cache::put($cacheKey, $listings, now()->addMinutes(5));
        }

        return $listings;
    }

    private function baseUri(): string
    {
        return (string) $this->config->get('services.idx.base_uri', '');
    }

    private function token(): string
    {
        return (string) $this->config->get('services.idx.token', '');
    }

    private function connection(): PendingRequest
    {
        return Http::retry(3, 250)
            ->timeout(10)
            ->baseUrl(rtrim($this->baseUri(), '/'))
            ->withToken($this->token())
            ->acceptJson();
    }

    /**
     * @return array<int, array{
     *     listing_key: string|null,
     *     address: string|null,
     *     city: string|null,
     *     state: string|null,
     *     postal_code: string|null,
     *     list_price: float|int|string|null,
     *     status: string|null,
     *     property_type: string|null,
     *     property_sub_type: string|null,
     *     list_office_name: string|null,
     *     remarks: string|null,
     *     modified_at: CarbonImmutable|null,
     *     virtual_tour_url: string|null,
     * }>
     */
    private function retrieveListings(int $limit): array
    {
        try {
            $response = $this->connection()->get('Property', [
                '$top' => $limit,
                '$orderby' => 'ModificationTimestamp desc',
            ]);

            if ($response->failed()) {
                return [];
            }

            $payload = $response->json('value');

            if (! is_array($payload)) {
                return [];
            }

            $limited = array_slice($payload, 0, $limit);

            return array_values(array_map(
                fn (array $listing): array => $this->transformListing($listing),
                array_filter($limited, 'is_array')
            ));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $listing
     * @return array{
     *     listing_key: string|null,
     *     address: string|null,
     *     city: string|null,
     *     state: string|null,
     *     postal_code: string|null,
     *     list_price: float|int|string|null,
     *     status: string|null,
     *     property_type: string|null,
     *     property_sub_type: string|null,
     *     list_office_name: string|null,
     *     remarks: string|null,
     *     modified_at: CarbonImmutable|null,
     *     virtual_tour_url: string|null,
     * }
     */
    private function transformListing(array $listing): array
    {
        $remarks = Arr::get($listing, 'PublicRemarks');

        return [
            'listing_key' => Arr::get($listing, 'ListingKey'),
            'address' => Arr::get($listing, 'UnparsedAddress') ?? $this->buildAddress($listing),
            'city' => Arr::get($listing, 'City'),
            'state' => Arr::get($listing, 'StateOrProvince'),
            'postal_code' => Arr::get($listing, 'PostalCode'),
            'list_price' => Arr::get($listing, 'ListPrice'),
            'status' => Arr::get($listing, 'StandardStatus') ?? Arr::get($listing, 'ContractStatus'),
            'property_type' => Arr::get($listing, 'PropertyType'),
            'property_sub_type' => Arr::get($listing, 'PropertySubType'),
            'list_office_name' => Arr::get($listing, 'ListOfficeName'),
            'remarks' => is_string($remarks) ? Str::limit(trim($remarks), 220) : null,
            'modified_at' => CarbonImmutable::make(Arr::get($listing, 'ModificationTimestamp')),
            'virtual_tour_url' => Arr::get($listing, 'VirtualTourURLBranded') ?? Arr::get($listing, 'VirtualTourURLUnbranded'),
        ];
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    private function buildAddress(array $listing): ?string
    {
        $street = array_filter([
            Arr::get($listing, 'StreetNumber'),
            Arr::get($listing, 'StreetDirPrefix'),
            Arr::get($listing, 'StreetName'),
            Arr::get($listing, 'StreetSuffix'),
        ]);

        if ($street === []) {
            return null;
        }

        $cityLine = array_filter([
            Arr::get($listing, 'City'),
            Arr::get($listing, 'StateOrProvince'),
            Arr::get($listing, 'PostalCode'),
        ]);

        $address = implode(' ', $street);

        if ($cityLine !== []) {
            $address .= ', '.implode(', ', $cityLine);
        }

        return $address;
    }
}
