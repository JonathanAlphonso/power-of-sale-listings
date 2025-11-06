<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Models\Municipality;
use App\Services\Idx\IdxClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class ImportIdxPowerOfSale implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200; // 20 minutes

    public function __construct(public int $pageSize = 500, public int $maxPages = 200) {}

    public function handle(IdxClient $idx): void
    {
        $top = max(1, min($this->pageSize, 100));
        $skip = 0;
        $pages = 0;
        $next = null;
        $processed = 0;

        Cache::put('idx.import.pos', [
            'status' => 'running',
            'items_total' => 0,
            'pages' => 0,
            'started_at' => now()->toISOString(),
        ], now()->addMinutes(10));

        do {
            $pages++;
            $page = $this->fetchPowerOfSalePage($idx, $top, $skip, $next);
            $batch = $page['items'];
            $next = $page['next'];

            if ($batch === []) {
                break;
            }

            foreach ($batch as $raw) {
                if (! is_array($raw)) {
                    continue;
                }

                $this->upsertListingFromRaw($idx, $raw);
                $processed++;
            }

            $skip += $top;
            Cache::put('idx.import.pos', [
                'status' => 'running',
                'items_total' => $processed,
                'pages' => $pages,
                'last_at' => now()->toISOString(),
            ], now()->addMinutes(10));
        } while ((count($batch) === $top || $next !== null) && $pages < $this->maxPages);

        Cache::put('idx.import.pos', [
            'status' => 'completed',
            'items_total' => $processed,
            'pages' => $pages,
            'finished_at' => now()->toISOString(),
        ], now()->addMinutes(10));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchPowerOfSaleBatch(IdxClient $idx, int $top, int $skip): array
    {
        // Mirror the query used by fetchPowerOfSaleListings, but return raw items
        $select = implode(',', [
            'ListingKey', 'OriginatingSystemName', 'ListingId', 'StandardStatus', 'MlsStatus', 'ContractStatus',
            'PropertyType', 'PropertySubType', 'ArchitecturalStyle', 'StreetNumber', 'StreetName', 'UnitNumber', 'City',
            'CityRegion', 'PostalCode', 'StateOrProvince', 'DaysOnMarket', 'BedroomsTotal', 'BathroomsTotalInteger',
            'LivingAreaRange', 'ListPrice', 'OriginalListPrice', 'ClosePrice', 'PreviousListPrice', 'PriceChangeTimestamp',
            'ModificationTimestamp', 'UnparsedAddress', 'InternetAddressDisplayYN', 'ParcelNumber', 'PublicRemarks',
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

        $response = \Http::retry(3, 500)
            ->timeout(30)
            ->baseUrl(rtrim((string) config('services.idx.base_uri', ''), '/'))
            ->withToken((string) config('services.idx.token', ''))
            ->acceptJson()
            ->withHeaders(['Prefer' => 'odata.maxpagesize=500'])
            ->get('Property', [
                '$select' => $select,
                '$filter' => $filter,
                '$orderby' => 'ModificationTimestamp,ListingKey',
                '$top' => $top,
                '$skip' => $skip,
            ]);

        if ($response->failed()) {
            return [];
        }

        $payload = $response->json('value');

        return is_array($payload) ? array_values(array_filter($payload, 'is_array')) : [];
    }

    /**
     * Fetch a page of results. If nextUrl is provided, it is used directly (relative to base URI).
     *
     * @return array{items: array<int, array<string, mixed>>, next: ?string}
     */
    protected function fetchPowerOfSalePage(IdxClient $idx, int $top, int $skip, ?string $nextUrl = null): array
    {
        // Base query components
        $select = implode(',', [
            'ListingKey', 'OriginatingSystemName', 'ListingId', 'StandardStatus', 'MlsStatus', 'ContractStatus',
            'PropertyType', 'PropertySubType', 'ArchitecturalStyle', 'StreetNumber', 'StreetName', 'UnitNumber', 'City',
            'CityRegion', 'PostalCode', 'StateOrProvince', 'DaysOnMarket', 'BedroomsTotal', 'BathroomsTotalInteger',
            'LivingAreaRange', 'ListPrice', 'OriginalListPrice', 'ClosePrice', 'PreviousListPrice', 'PriceChangeTimestamp',
            'ModificationTimestamp', 'UnparsedAddress', 'InternetAddressDisplayYN', 'ParcelNumber', 'PublicRemarks',
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

        $request = \Http::retry(3, 500)
            ->timeout(30)
            ->withToken((string) config('services.idx.token', ''))
            ->acceptJson();

        $response = $nextUrl !== null
            ? (function () use ($request, $nextUrl) {
                $next = (string) $nextUrl;
                $absolute = str_starts_with($next, 'http')
                    ? $next
                    : rtrim((string) config('services.idx.base_uri', ''), '/').'/'.ltrim($next, '/');

                return $request->withHeaders(['Prefer' => 'odata.maxpagesize=500'])->get($absolute);
            })()
            : $request->baseUrl(rtrim((string) config('services.idx.base_uri', ''), '/'))
                ->withHeaders(['Prefer' => 'odata.maxpagesize=500'])
                ->get('Property', [
                    '$select' => $select,
                    '$filter' => $filter,
                    '$orderby' => 'ModificationTimestamp,ListingKey',
                    '$top' => $top,
                    '$skip' => $skip,
                ]);

        if ($response->failed()) {
            return ['items' => [], 'next' => null];
        }

        $items = $response->json('value');
        $root = $response->json();

        $items = is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
        $next = (is_array($root) && isset($root['@odata.nextLink']) && is_string($root['@odata.nextLink']) && $root['@odata.nextLink'] !== '')
            ? $root['@odata.nextLink']
            : null;

        return ['items' => $items, 'next' => $next];
    }

    protected function upsertListingFromRaw(IdxClient $idx, array $raw): void
    {
        $key = Arr::get($raw, 'ListingKey');
        if (! is_string($key) || $key === '') {
            return;
        }

        $city = Arr::get($raw, 'City');
        $province = Arr::get($raw, 'StateOrProvince') ?: 'ON';
        $municipalityId = null;

        if (is_string($city) && $city !== '') {
            $municipality = Municipality::query()->firstOrCreate([
                'name' => $city,
                'province' => $province,
            ]);
            $municipalityId = $municipality->id;
        }

        $attrs = $this->mapListingAttributes($idx, $raw);

        $boardCode = Arr::get($raw, 'OriginatingSystemName') ?? Arr::get($raw, 'SourceSystemName') ?? 'UNKNOWN';
        $mlsNumber = Arr::get($raw, 'ListingId') ?? Arr::get($raw, 'MLSNumber') ?? $key;

        /** @var Listing|null $listing */
        $listing = Listing::withTrashed()->where('external_id', $key)->first();

        if ($listing === null) {
            $listing = Listing::withTrashed()
                ->where('board_code', $boardCode)
                ->where('mls_number', $mlsNumber)
                ->first();
        }

        if ($listing === null) {
            $listing = new Listing;
            $listing->external_id = $key;
        }

        if (method_exists($listing, 'trashed') && $listing->trashed()) {
            $listing->restore();
        }

        $listing->fill(array_filter([
            'municipality_id' => $municipalityId,
            'board_code' => Arr::get($raw, 'OriginatingSystemName') ?? Arr::get($raw, 'SourceSystemName') ?? 'UNKNOWN',
            'mls_number' => Arr::get($raw, 'ListingId') ?? Arr::get($raw, 'MLSNumber') ?? $key,
            'display_status' => $attrs['status'] ?? null,
            'status_code' => $attrs['status'] ?? null,
            'property_type' => $attrs['property_type'] ?? null,
            'property_style' => $attrs['property_sub_type'] ?? null,
            'street_number' => Arr::get($raw, 'StreetNumber'),
            'street_name' => Arr::get($raw, 'StreetName'),
            'street_address' => $attrs['address'] ?? null,
            'unit_number' => Arr::get($raw, 'UnitNumber'),
            'city' => $attrs['city'] ?? null,
            'province' => $province,
            'postal_code' => $attrs['postal_code'] ?? null,
            'list_price' => $attrs['list_price'] ?? null,
            'original_list_price' => Arr::get($raw, 'OriginalListPrice'),
            'bedrooms' => Arr::get($raw, 'BedroomsTotal'),
            'bathrooms' => Arr::get($raw, 'BathroomsTotalInteger'),
            'days_on_market' => Arr::get($raw, 'DaysOnMarket'),
            'modified_at' => $attrs['modified_at'] ?? null,
            'payload' => $raw,
        ], fn ($v) => $v !== null));

        try {
            $listing->save();
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle race condition or missed match on unique (board_code, mls_number)
            if (str_contains(strtolower($e->getMessage()), 'duplicate entry') &&
                str_contains($e->getMessage(), 'listings_board_code_mls_number_unique')) {
                $conflict = Listing::withTrashed()
                    ->where('board_code', $boardCode)
                    ->where('mls_number', $mlsNumber)
                    ->first();

                if ($conflict !== null) {
                    if (method_exists($conflict, 'trashed') && $conflict->trashed()) {
                        $conflict->restore();
                    }
                    $conflict->fill($listing->getAttributes());
                    $listing = $conflict;
                    $listing->save();
                } else {
                    throw $e;
                }
            } else {
                throw $e;
            }
        }

        // Record status history (best-effort)
        $listing->recordStatusHistory($listing->status_code, $listing->display_status, $raw, $listing->modified_at ?? now());
    }

    protected function mapListingAttributes(IdxClient $idx, array $raw): array
    {
        // Use IdxClient's transform to maintain consistency.
        $ref = new \ReflectionClass($idx);
        $method = $ref->getMethod('transformListing');
        $method->setAccessible(true);

        /** @var array<string,mixed> $mapped */
        $mapped = $method->invoke($idx, $raw);

        return is_array($mapped) ? $mapped : [];
    }
}
