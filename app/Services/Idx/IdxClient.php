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
     *     image_url: string|null,
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

    /**
     * Fetch recent Power of Sale listings (remark-based filter, deterministic order).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchPowerOfSaleListings(int $limit = 4): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $cacheKey = sprintf('idx.pos.listings.%d', $limit);

        if (Cache::has($cacheKey)) {
            /** @var array<int, array<string, mixed>> $cached */
            $cached = Cache::get($cacheKey, []);

            return $cached;
        }

        $listings = $this->retrievePowerOfSaleListings($limit);

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
     *     image_url: string|null,
     * }>
     */
    private function retrieveListings(int $limit): array
    {
        try {
            $select = implode(',', [
                'ListingKey',
                'ListingId',
                'OriginatingSystemName',
                'UnparsedAddress',
                'StreetNumber',
                'StreetDirPrefix',
                'StreetName',
                'StreetSuffix',
                'UnitNumber',
                'City',
                'StateOrProvince',
                'PostalCode',
                'StandardStatus',
                'ModificationTimestamp',
                'ListPrice',
                'OriginalListPrice',
                'PropertyType',
                'PropertySubType',
                'ListOfficeName',
                'PublicRemarks',
                'VirtualTourURLBranded',
                'VirtualTourURLUnbranded',
            ]);

            $response = $this->connection()->timeout(6)->get('Property', [
                // Only return live/on-market listings
                '$filter' => "StandardStatus eq 'Active'",
                '$select' => $select,
                '$top' => $limit,
                // Prefer deterministic ordering for stable paging
                '$orderby' => 'ModificationTimestamp,ListingKey',
            ]);

            // Metrics: record Property request status (best-effort)
            try {
                $this->recordHttpMetrics('property', $response->status());
            } catch (\Throwable) {
                // ignore
            }

            if ($response->failed()) {
                return [];
            }

            $payload = $response->json('value');

            if (! is_array($payload)) {
                return [];
            }

            // Apply a defensive filter in case upstream ignores StandardStatus filter
            $filtered = array_values(array_filter($payload, function ($item): bool {
                if (! is_array($item)) {
                    return false;
                }

                $status = Arr::get($item, 'StandardStatus');

                return is_string($status) && strtolower($status) === 'active';
            }));

            $limited = array_slice($filtered, 0, $limit);

            $transformed = array_values(array_map(
                fn (array $listing): array => $this->transformListing($listing),
                array_filter($limited, 'is_array')
            ));

            // Attach a primary image (if available) for display on the homepage.
            $listingKeys = array_values(array_filter(array_map(
                fn (array $item): ?string => is_string($item['listing_key'] ?? null) ? (string) $item['listing_key'] : null,
                $transformed
            )));

            if ($listingKeys !== []) {
                $imageMap = $this->retrievePrimaryImagesByListingKey($listingKeys);

                foreach ($transformed as &$item) {
                    $key = $item['listing_key'] ?? null;
                    $item['image_url'] = is_string($key) && isset($imageMap[$key]) ? (string) $imageMap[$key] : null;
                }
                unset($item);
            }

            return $transformed;
        } catch (Throwable $e) {
            try {
                $this->recordHttpMetrics('property', null, $e->getMessage());
            } catch (\Throwable) {
                // ignore
            }

            return [];
        }
    }

    /**
     * Power of Sale: query by remarks without forcing StandardStatus = Active.
     *
     * @return array<int, array<string, mixed>>
     */
    private function retrievePowerOfSaleListings(int $limit): array
    {
        try {
            $select = implode(',', [
                'ListingKey',
                'ListingId',
                'OriginatingSystemName',
                'UnparsedAddress',
                'StreetNumber',
                'StreetDirPrefix',
                'StreetName',
                'StreetSuffix',
                'UnitNumber',
                'City',
                'StateOrProvince',
                'PostalCode',
                'StandardStatus',
                'MlsStatus',
                'ContractStatus',
                'ModificationTimestamp',
                'ListPrice',
                'OriginalListPrice',
                'PropertyType',
                'PropertySubType',
                'ListOfficeName',
                'PublicRemarks',
                'VirtualTourURLBranded',
                'VirtualTourURLUnbranded',
                'TransactionType',
            ]);

            $filter = 'PublicRemarks ne null and ('
                ."contains(PublicRemarks,'power of sale') or "
                ."contains(PublicRemarks,'Power of Sale') or "
                ."contains(PublicRemarks,'POWER OF SALE') or "
                ."contains(PublicRemarks,'Power-of-Sale') or "
                ."contains(PublicRemarks,'Power-of-sale') or "
                ."contains(PublicRemarks,'P.O.S') or "
                ."contains(PublicRemarks,' POS ') or "
                ."contains(PublicRemarks,' POS,') or "
                ."contains(PublicRemarks,' POS.') or "
                ."contains(PublicRemarks,' POS-')"
                .") and TransactionType eq 'For Sale'";

            $response = $this->connection()->timeout(6)->get('Property', [
                '$select' => $select,
                '$filter' => $filter,
                '$top' => $limit,
                '$orderby' => 'ModificationTimestamp,ListingKey',
            ]);

            try {
                $this->recordHttpMetrics('property', $response->status());
            } catch (\Throwable) {
                // ignore
            }

            if ($response->failed()) {
                return [];
            }

            $payload = $response->json('value');
            if (! is_array($payload)) {
                return [];
            }

            $limited = array_slice(array_values(array_filter($payload, 'is_array')), 0, $limit);

            $transformed = array_values(array_map(
                fn (array $listing): array => $this->transformListing($listing),
                $limited
            ));

            $listingKeys = array_values(array_filter(array_map(
                fn (array $item): ?string => is_string($item['listing_key'] ?? null) ? (string) $item['listing_key'] : null,
                $transformed
            )));

            if ($listingKeys !== []) {
                $imageMap = $this->retrievePrimaryImagesByListingKey($listingKeys);

                foreach ($transformed as &$item) {
                    $key = $item['listing_key'] ?? null;
                    $item['image_url'] = is_string($key) && isset($imageMap[$key]) ? (string) $imageMap[$key] : null;
                }
                unset($item);
            }

            return $transformed;
        } catch (Throwable $e) {
            try {
                $this->recordHttpMetrics('property', null, $e->getMessage());
            } catch (\Throwable) {
            }

            return [];
        }
    }

    /**
     * Retrieve primary image URLs for a set of listing keys.
     *
     * @param  array<int, string>  $listingKeys
     * @return array<string, string> map of ListingKey => MediaURL
     */
    private function retrievePrimaryImagesByListingKey(array $listingKeys): array
    {
        $listingKeys = array_values(array_unique(array_filter($listingKeys, 'is_string')));

        if ($listingKeys === []) {
            return [];
        }

        try {
            $result = [];

            foreach ($listingKeys as $key) {
                $escapedKey = str_replace("'", "''", $key);

                $select = implode(',', [
                    'MediaURL',
                    'MediaType',
                    'ResourceName',
                    'ResourceRecordKey',
                    'MediaModificationTimestamp',
                ]);

                $response = $this->connection()
                    ->timeout(3)
                    ->get('Media', [
                        '$top' => 1,
                        '$orderby' => 'MediaModificationTimestamp desc',
                        '$filter' => "ResourceName eq 'Property' and ResourceRecordKey eq '{$escapedKey}' and MediaCategory eq 'Photo' and MediaStatus eq 'Active'",
                        '$select' => $select,
                    ]);

                try {
                    $this->recordHttpMetrics('media', $response->status());
                } catch (\Throwable) {
                    // ignore
                }

                if ($response->failed()) {
                    continue;
                }

                $item = $response->json('value.0');

                if (! is_array($item)) {
                    continue;
                }

                $url = $item['MediaURL'] ?? null;
                $type = $item['MediaType'] ?? null;
                $resource = $item['ResourceName'] ?? null;

                if (is_string($url)
                    && is_string($type)
                    && str_starts_with(strtolower($type), 'image/')
                    && is_string($resource)
                    && strtolower($resource) === 'property') {
                    $result[$key] = $url;
                }
            }

            return $result;
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
     *     image_url: string|null,
     * }
     */
    private function transformListing(array $listing): array
    {
        $remarks = Arr::get($listing, 'PublicRemarks');

        $standard = Arr::get($listing, 'StandardStatus');
        $mlsStatus = Arr::get($listing, 'MlsStatus');
        $contract = Arr::get($listing, 'ContractStatus');

        return [
            'listing_key' => Arr::get($listing, 'ListingKey'),
            'address' => Arr::get($listing, 'UnparsedAddress') ?? $this->buildAddress($listing),
            'city' => Arr::get($listing, 'City'),
            'state' => Arr::get($listing, 'StateOrProvince'),
            'postal_code' => Arr::get($listing, 'PostalCode'),
            'list_price' => Arr::get($listing, 'ListPrice'),
            // Prefer RESO StandardStatus; fall back to board-specific labels when needed
            'status' => is_string($standard) && $standard !== ''
                ? $standard
                : (is_string($mlsStatus) && $mlsStatus !== ''
                    ? $mlsStatus
                    : (is_string($contract) && $contract !== '' ? $contract : null)),
            'property_type' => Arr::get($listing, 'PropertyType'),
            'property_sub_type' => Arr::get($listing, 'PropertySubType'),
            'list_office_name' => Arr::get($listing, 'ListOfficeName'),
            'remarks' => is_string($remarks) ? Str::limit(trim($remarks), 220) : null,
            'modified_at' => CarbonImmutable::make(Arr::get($listing, 'ModificationTimestamp')),
            'virtual_tour_url' => Arr::get($listing, 'VirtualTourURLBranded') ?? Arr::get($listing, 'VirtualTourURLUnbranded'),
            'image_url' => null,
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

    private function recordHttpMetrics(string $scope, ?int $status, ?string $error = null): void
    {
        $prefix = sprintf('idx.metrics.%s.', $scope);

        // Sliding window 24h
        Cache::put('idx.metrics.window_started', Cache::get('idx.metrics.window_started', now()->toIso8601String()), now()->addDay());

        Cache::put($prefix.'total', (int) Cache::get($prefix.'total', 0) + 1, now()->addDay());

        if ($status !== null) {
            Cache::put('idx.metrics.last_status', $status, now()->addDay());
            Cache::put('idx.metrics.last_at', now()->toIso8601String(), now()->addDay());

            if ($status >= 200 && $status < 300) {
                Cache::put($prefix.'success', (int) Cache::get($prefix.'success', 0) + 1, now()->addDay());
            } elseif ($status === 429) {
                Cache::put($prefix.'429', (int) Cache::get($prefix.'429', 0) + 1, now()->addDay());
            } elseif ($status >= 500) {
                Cache::put($prefix.'5xx', (int) Cache::get($prefix.'5xx', 0) + 1, now()->addDay());
            } else {
                Cache::put($prefix.'other', (int) Cache::get($prefix.'other', 0) + 1, now()->addDay());
            }
        } else {
            Cache::put('idx.metrics.last_error', Str::limit((string) $error, 180), now()->addDay());
            Cache::put('idx.metrics.last_at', now()->toIso8601String(), now()->addDay());
            Cache::put($prefix.'other', (int) Cache::get($prefix.'other', 0) + 1, now()->addDay());
        }
    }
}
