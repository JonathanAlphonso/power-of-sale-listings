<?php

declare(strict_types=1);

namespace App\Services\Idx;

use App\Support\ResoFilters;
use App\Support\ResoSelects;
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
    public function __construct(
        private Repository $config,
        private ListingTransformer $transformer,
    ) {}

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

        /** @var array<int, array<string, mixed>> $listings */
        $listings = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($limit): array {
            return $this->retrieveListings($limit);
        });

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

        /** @var array<int, array<string, mixed>> $listings */
        $listings = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($limit): array {
            return $this->retrievePowerOfSaleListings($limit);
        });

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
        return Http::retry(2, 200)
            ->timeout(10)
            ->baseUrl(rtrim($this->baseUri(), '/'))
            ->withToken($this->token())
            ->acceptJson()
            ->withHeaders([
                'OData-Version' => '4.0',
            ]);
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
            $select = ResoSelects::propertyCard();

            // Keep this request snappy to avoid overall page timeouts
            $response = $this->connection()->retry(1, 200)->timeout(4)->get('Property', [
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
                fn (array $listing): array => $this->transformer->transform($listing),
                array_filter($limited, 'is_array')
            ));

            // Attach a primary image (if available) for display on the homepage.
            $listingKeys = array_values(array_filter(array_map(
                fn (array $item): ?string => is_string($item['listing_key'] ?? null) ? (string) $item['listing_key'] : null,
                $transformed
            )));

            if ($listingKeys !== []) {
                $imageMap = $this->retrievePrimaryImagesByListingKeyPooled($listingKeys);

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
            $select = ResoSelects::propertyPowerOfSaleCard();

            $filter = ResoFilters::powerOfSale();

            logger()->info('idx.pos_query_start', [
                'limit' => $limit,
                'filter_length' => strlen($filter),
            ]);

            $start = microtime(true);

            // Keep this tighter than PHP's execution limit so failures are handled gracefully
            $response = $this->connection()->retry(1, 200)->timeout(8)->get('Property', [
                '$select' => $select,
                '$filter' => $filter,
                '$top' => $limit,
                '$orderby' => 'ModificationTimestamp,ListingKey',
            ]);

            $duration = round((microtime(true) - $start) * 1000);

            logger()->info('idx.pos_query_complete', [
                'status' => $response->status(),
                'duration_ms' => $duration,
                'body_length' => strlen($response->body()),
            ]);

            try {
                $this->recordHttpMetrics('property', $response->status());
            } catch (\Throwable) {
                // ignore
            }

            if ($response->failed()) {
                logger()->warning('idx.pos_query_failed', [
                    'status' => $response->status(),
                    'duration_ms' => $duration,
                ]);

                return [];
            }

            $payload = $response->json('value');
            if (! is_array($payload)) {
                return [];
            }

            $limited = array_slice(array_values(array_filter($payload, 'is_array')), 0, $limit);

            $transformed = array_values(array_map(
                fn (array $listing): array => $this->transformer->transform($listing),
                $limited
            ));

            $listingKeys = array_values(array_filter(array_map(
                fn (array $item): ?string => is_string($item['listing_key'] ?? null) ? (string) $item['listing_key'] : null,
                $transformed
            )));

            if ($listingKeys !== []) {
                $imageMap = $this->retrievePrimaryImagesByListingKeyPooled($listingKeys);

                foreach ($transformed as &$item) {
                    $key = $item['listing_key'] ?? null;
                    $item['image_url'] = is_string($key) && isset($imageMap[$key]) ? (string) $imageMap[$key] : null;
                }
                unset($item);
            }

            return $transformed;
        } catch (Throwable $e) {
            logger()->error('idx.pos_query_exception', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
                'limit' => $limit,
            ]);

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

            // Attempt to satisfy as many as possible from cache first.
            $missing = [];
            foreach ($listingKeys as $key) {
                $cached = Cache::get('idx.media.primary_url.'.$key);
                if (is_string($cached) && $cached !== '') {
                    $result[$key] = $cached;
                } else {
                    $missing[] = $key;
                }
            }

            $select = implode(',', [
                'MediaURL',
                'MediaType',
                'ResourceName',
                'ResourceRecordKey',
                'MediaModificationTimestamp',
            ]);

            // Enforce a small overall time budget for media lookups.
            $started = microtime(true);
            $maxSeconds = 2.0;

            foreach ($missing as $key) {
                if ((microtime(true) - $started) >= $maxSeconds) {
                    break;
                }

                $escapedKey = str_replace("'", "''", $key);

                $response = $this->connection()
                    ->retry(0, 0)
                    ->timeout(2)
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
                    Cache::put('idx.media.primary_url.'.$key, $url, now()->addMinutes(15));
                }
            }

            return $result;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Retrieve primary image URLs using small concurrent batches for responsiveness.
     *
     * @param  array<int, string>  $listingKeys
     * @return array<string, string>
     */
    private function retrievePrimaryImagesByListingKeyPooled(array $listingKeys): array
    {
        $listingKeys = array_values(array_unique(array_filter($listingKeys, 'is_string')));

        if ($listingKeys === []) {
            return [];
        }

        try {
            $result = [];

            // Serve cached values when available and collect remaining keys to fetch.
            $remaining = [];
            foreach ($listingKeys as $key) {
                $cached = Cache::get('idx.media.primary_url.'.$key);
                if (is_string($cached) && $cached !== '') {
                    $result[$key] = $cached;
                } else {
                    $remaining[] = $key;
                }
            }

            $select = implode(',', [
                'MediaURL',
                'MediaType',
                'ResourceName',
                'ResourceRecordKey',
                'MediaModificationTimestamp',
            ]);

            $started = microtime(true);
            $maxSeconds = 2.0;

            foreach (array_chunk($remaining, 5) as $chunk) {
                if ((microtime(true) - $started) >= $maxSeconds) {
                    break;
                }

                $responses = \Illuminate\Support\Facades\Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($chunk, $select) {
                    $requests = [];

                    foreach ($chunk as $key) {
                        $escapedKey = str_replace("'", "''", $key);

                        $requests[] = $pool
                            ->as($key)
                            ->retry(0, 0)
                            ->timeout(2)
                            ->baseUrl(rtrim($this->baseUri(), '/'))
                            ->withToken($this->token())
                            ->acceptJson()
                            ->withHeaders([
                                'OData-Version' => '4.0',
                            ])
                            ->get('Media', [
                                '$top' => 1,
                                '$orderby' => 'MediaModificationTimestamp desc',
                                '$filter' => "ResourceName eq 'Property' and ResourceRecordKey eq '{$escapedKey}' and MediaCategory eq 'Photo' and MediaStatus eq 'Active'",
                                '$select' => $select,
                            ]);
                    }

                    return $requests;
                });

                foreach ($chunk as $key) {
                    /** @var \Illuminate\Http\Client\Response|null $response */
                    $response = $responses[$key] ?? null;
                    if (! $response instanceof \Illuminate\Http\Client\Response) {
                        continue;
                    }

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
                        Cache::put('idx.media.primary_url.'.$key, $url, now()->addMinutes(15));
                    }
                }
            }

            return $result;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Fetch photo media records for a specific Property listing key.
     *
     * @return array<int, array{url:string,label:?string,type:?string,size:?string,modified_at:?string,media_key:?string}>
     */
    public function fetchPropertyMedia(string $listingKey, int $limit = 25): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        try {
            $escapedKey = str_replace("'", "''", $listingKey);

            $select = implode(',', [
                'MediaURL',
                'MediaType',
                'ResourceName',
                'ResourceRecordKey',
                'MediaModificationTimestamp',
                'ImageSizeDescription',
                'LongDescription',
                'ShortDescription',
                'MediaKey',
                'MediaCategory',
                'MediaStatus',
            ]);

            $response = $this->connection()->retry(1, 200)->timeout(8)->get('Media', [
                // Only Property photos that are currently active. Prefer a single size for consistency.
                '$filter' => "ResourceName eq 'Property' and ResourceRecordKey eq '{$escapedKey}' and MediaCategory eq 'Photo' and MediaStatus eq 'Active' and ImageSizeDescription eq 'Large'",
                '$select' => $select,
                '$orderby' => 'MediaModificationTimestamp,MediaKey',
                '$top' => max(1, min($limit, 50)),
            ]);

            try {
                $this->recordHttpMetrics('media', $response->status());
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

            $items = array_values(array_filter($payload, 'is_array'));

            return array_values(array_map(function (array $item): array {
                $url = $item['MediaURL'] ?? null;
                $label = $item['LongDescription'] ?? ($item['ShortDescription'] ?? null);
                $type = $item['MediaType'] ?? null;
                $size = $item['ImageSizeDescription'] ?? null;
                $modified = $item['MediaModificationTimestamp'] ?? null;
                $key = $item['MediaKey'] ?? null;

                return [
                    'url' => is_string($url) ? $url : '',
                    'label' => is_string($label) && $label !== '' ? $label : null,
                    'type' => is_string($type) ? $type : null,
                    'size' => is_string($size) ? $size : null,
                    'modified_at' => is_string($modified) ? $modified : null,
                    'media_key' => is_string($key) ? $key : null,
                ];
            }, $items));
        } catch (Throwable) {
            return [];
        }
    }

// Address building is handled by the ListingTransformer

    /**
     * Fetch a page of media records using cursor-based pagination.
     *
     * This method is designed for replication/backfill scenarios where we need to
     * page through all media records efficiently. Uses ModificationTimestamp,MediaKey
     * for deterministic ordering and cursor-based pagination as per API best practices.
     *
     * @param  string|null  $cursorTimestamp  ISO 8601 timestamp to resume from (null for start)
     * @param  string|null  $cursorMediaKey  MediaKey to resume from within same timestamp
     * @param  int  $limit  Number of records per page (default 100, max 1000)
     * @return array{items: array<int, array{media_key:string,resource_key:string,url:string,type:?string,size:?string,modified_at:string}>, cursor: array{timestamp:?string,media_key:?string}, has_more: bool}
     */
    public function fetchMediaPage(
        ?string $cursorTimestamp = null,
        ?string $cursorMediaKey = null,
        int $limit = 100,
    ): array {
        $emptyResult = ['items' => [], 'cursor' => ['timestamp' => null, 'media_key' => null], 'has_more' => false];

        if (! $this->isEnabled()) {
            return $emptyResult;
        }

        $limit = max(1, min($limit, 1000));

        try {
            $select = implode(',', [
                'MediaKey',
                'MediaURL',
                'MediaType',
                'ResourceName',
                'ResourceRecordKey',
                'MediaModificationTimestamp',
                'ImageSizeDescription',
                'LongDescription',
                'ShortDescription',
                'MediaCategory',
                'MediaStatus',
            ]);

            // Build cursor filter for proper pagination (timestamp + key based)
            $cursorTs = $cursorTimestamp ?? '1970-01-01T00:00:00Z';
            $cursorKey = str_replace("'", "''", $cursorMediaKey ?? '');

            // Filter: Only Property photos that are active
            // Cursor: (ModificationTimestamp > cursor) OR (ModificationTimestamp = cursor AND MediaKey > cursorKey)
            $filter = "ResourceName eq 'Property' and MediaCategory eq 'Photo' and MediaStatus eq 'Active' and ImageSizeDescription eq 'Large'";
            $filter .= " and (MediaModificationTimestamp gt {$cursorTs}";
            $filter .= " or (MediaModificationTimestamp eq {$cursorTs} and MediaKey gt '{$cursorKey}'))";

            $response = $this->connection()->retry(1, 200)->timeout(15)->get('Media', [
                '$filter' => $filter,
                '$select' => $select,
                '$orderby' => 'MediaModificationTimestamp,MediaKey',
                '$top' => $limit,
            ]);

            try {
                $this->recordHttpMetrics('media', $response->status());
            } catch (\Throwable) {
                // ignore
            }

            if ($response->failed()) {
                return $emptyResult;
            }

            $payload = $response->json('value');
            if (! is_array($payload)) {
                return $emptyResult;
            }

            $items = [];
            $lastTimestamp = null;
            $lastMediaKey = null;

            foreach ($payload as $record) {
                if (! is_array($record)) {
                    continue;
                }

                $mediaKey = $record['MediaKey'] ?? null;
                $resourceKey = $record['ResourceRecordKey'] ?? null;
                $url = $record['MediaURL'] ?? null;
                $modifiedAt = $record['MediaModificationTimestamp'] ?? null;

                if (! is_string($mediaKey) || ! is_string($resourceKey) || ! is_string($url) || ! is_string($modifiedAt)) {
                    continue;
                }

                $items[] = [
                    'media_key' => $mediaKey,
                    'resource_key' => $resourceKey, // This is the ListingKey
                    'url' => $url,
                    'label' => is_string($record['LongDescription'] ?? null) ? $record['LongDescription'] : (is_string($record['ShortDescription'] ?? null) ? $record['ShortDescription'] : null),
                    'type' => is_string($record['MediaType'] ?? null) ? $record['MediaType'] : null,
                    'size' => is_string($record['ImageSizeDescription'] ?? null) ? $record['ImageSizeDescription'] : null,
                    'modified_at' => $modifiedAt,
                ];

                $lastTimestamp = $modifiedAt;
                $lastMediaKey = $mediaKey;
            }

            return [
                'items' => $items,
                'cursor' => [
                    'timestamp' => $lastTimestamp,
                    'media_key' => $lastMediaKey,
                ],
                'has_more' => count($items) >= $limit,
            ];
        } catch (Throwable) {
            return $emptyResult;
        }
    }

    /**
     * Get listing keys that have media changes since a given timestamp.
     *
     * This is useful for identifying which listings need media sync updates
     * without downloading all media records.
     *
     * @param  string|null  $since  ISO 8601 timestamp (null for all)
     * @param  int  $limit  Max number of unique listing keys to return
     * @return array<int, string> Array of unique ListingKey values
     */
    public function getListingKeysWithMediaChanges(?string $since = null, int $limit = 500): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $allKeys = [];
        $cursorTimestamp = $since ?? '1970-01-01T00:00:00Z';
        $cursorMediaKey = null;
        $pageSize = min($limit * 2, 500); // Fetch more to account for duplicates

        // Page through media until we have enough unique listing keys
        while (count($allKeys) < $limit) {
            $page = $this->fetchMediaPage($cursorTimestamp, $cursorMediaKey, $pageSize);

            if (empty($page['items'])) {
                break;
            }

            foreach ($page['items'] as $item) {
                $key = $item['resource_key'];
                if (! in_array($key, $allKeys, true)) {
                    $allKeys[] = $key;
                    if (count($allKeys) >= $limit) {
                        break 2;
                    }
                }
            }

            if (! $page['has_more']) {
                break;
            }

            $cursorTimestamp = $page['cursor']['timestamp'];
            $cursorMediaKey = $page['cursor']['media_key'];
        }

        return $allKeys;
    }

    private function recordHttpMetrics(string $scope, ?int $status, ?string $error = null): void
    {
        $prefix = sprintf('idx.metrics.%s.', $scope);
        $ttl = now()->addDay();

        // Sliding window 24h
        Cache::put('idx.metrics.window_started', Cache::get('idx.metrics.window_started', now()->toIso8601String()), $ttl);

        $writes = [
            $prefix.'total' => (int) Cache::get($prefix.'total', 0) + 1,
        ];

        if ($status !== null) {
            $writes['idx.metrics.last_status'] = $status;
            $writes['idx.metrics.last_at'] = now()->toIso8601String();

            if ($status >= 200 && $status < 300) {
                $writes[$prefix.'success'] = (int) Cache::get($prefix.'success', 0) + 1;
            } elseif ($status === 429) {
                $writes[$prefix.'429'] = (int) Cache::get($prefix.'429', 0) + 1;
            } elseif ($status >= 500) {
                $writes[$prefix.'5xx'] = (int) Cache::get($prefix.'5xx', 0) + 1;
            } else {
                $writes[$prefix.'other'] = (int) Cache::get($prefix.'other', 0) + 1;
            }
        } else {
            $writes['idx.metrics.last_error'] = Str::limit((string) $error, 180);
            $writes['idx.metrics.last_at'] = now()->toIso8601String();
            $writes[$prefix.'other'] = (int) Cache::get($prefix.'other', 0) + 1;
        }

        try {
            $store = Cache::getStore();
            if (method_exists($store, 'putMany')) {
                $store->putMany($writes, $ttl);
            } else {
                foreach ($writes as $key => $value) {
                    Cache::put($key, $value, $ttl);
                }
            }
        } catch (\Throwable) {
            // Best-effort metrics only
        }
    }
}
