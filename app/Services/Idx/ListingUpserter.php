<?php

declare(strict_types=1);

namespace App\Services\Idx;

use App\Jobs\SyncIdxMediaForListing;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\Source;
use App\Support\BoardCode;
use App\Support\ListingDateResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class ListingUpserter
{
    public function __construct(
        private readonly ListingTransformer $transformer,
    ) {}

    /**
     * Upsert a listing from raw API data.
     *
     * @param  array<string, mixed>  $raw
     * @param  'idx'|'vow'  $sourceSlug
     * @param  bool  $requirePowerOfSale  If true, skip listings that don't match PoS remarks
     */
    public function upsert(
        array $raw,
        CarbonImmutable $syncedAt,
        string $sourceSlug = 'idx',
        bool $requirePowerOfSale = false,
    ): ?Listing {
        $key = Arr::get($raw, 'ListingKey');
        if (! is_string($key) || $key === '') {
            return null;
        }

        // Power of Sale check if required
        if ($requirePowerOfSale) {
            $publicRemarksRaw = Arr::get($raw, 'PublicRemarks');
            $remarks = is_string($publicRemarksRaw) ? $publicRemarksRaw : null;
            if (! \App\Support\ResoFilters::isPowerOfSaleRemarks($remarks)) {
                return null;
            }

            logger()->info("import_{$sourceSlug}_pos.pos_flagged", [
                'listing_key' => $key,
                'originating_system' => Arr::get($raw, 'OriginatingSystemName') ?? Arr::get($raw, 'SourceSystemName'),
                'transaction_type' => Arr::get($raw, 'TransactionType'),
                'remarks_preview' => $remarks !== null ? \Illuminate\Support\Str::limit($remarks, 160) : null,
            ]);
        }

        $municipalityId = $this->resolveMunicipality($raw);
        $attrs = $this->transformer->transform($raw);

        $boardCode = BoardCode::fromSystemName(
            Arr::get($raw, 'OriginatingSystemName')
                ?? Arr::get($raw, 'SourceSystemName')
                ?? Arr::get($raw, 'ListAOR')
        );
        $mlsNumber = Arr::get($raw, 'ListingId') ?? Arr::get($raw, 'MLSNumber') ?? $key;

        $listing = $this->findOrCreateListing($key, $boardCode, $mlsNumber);

        $listing->listing_key = $key;

        if (method_exists($listing, 'trashed') && $listing->trashed()) {
            $listing->restore();
        }

        $this->fillListingAttributes($listing, $raw, $attrs, $key, $municipalityId, $boardCode, $mlsNumber, $syncedAt);
        $this->applySourceWithPriority($listing, $sourceSlug);

        // Determine if anything actually changed (ignore noisy JSON payload changes)
        $dirtyKeys = array_keys($listing->getDirty());
        $effectiveDirty = array_values(array_diff($dirtyKeys, ['payload']));
        $statusChanged = in_array('status_code', $dirtyKeys, true) || in_array('display_status', $dirtyKeys, true);

        if ($effectiveDirty === []) {
            // Nothing meaningful changed; skip save and history/media work
            return $listing;
        }

        $this->saveListing($listing, $boardCode, $mlsNumber);

        // Record status history only when status fields changed
        if ($statusChanged) {
            $listing->recordStatusHistory($listing->status_code, $listing->display_status, $raw, $listing->modified_at ?? now());
        }

        // Sync media from the Media resource for this listing key.
        SyncIdxMediaForListing::dispatch((int) $listing->id, (string) $key)->onQueue('media');

        return $listing;
    }

    /**
     * Resolve municipality from raw data, creating if needed.
     *
     * @param  array<string, mixed>  $raw
     */
    private function resolveMunicipality(array $raw): ?int
    {
        $city = Arr::get($raw, 'City');
        $province = Arr::get($raw, 'StateOrProvince') ?: 'ON';

        if (! is_string($city) || $city === '') {
            return null;
        }

        $municipality = Municipality::query()->firstOrCreate([
            'name' => $city,
            'province' => $province,
        ]);

        return $municipality->id;
    }

    /**
     * Find existing listing or create new one.
     */
    private function findOrCreateListing(string $key, string $boardCode, string $mlsNumber): Listing
    {
        // First try by external_id (ListingKey)
        $listing = Listing::withTrashed()->where('external_id', $key)->first();

        if ($listing === null) {
            // Try by board_code + mls_number
            $listing = Listing::withTrashed()
                ->where('board_code', $boardCode)
                ->where('mls_number', $mlsNumber)
                ->first();
        }

        if ($listing === null) {
            $listing = new Listing;
            $listing->external_id = $key;
        }

        return $listing;
    }

    /**
     * Fill listing with attributes from raw API data.
     *
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $attrs
     */
    private function fillListingAttributes(
        Listing $listing,
        array $raw,
        array $attrs,
        string $key,
        ?int $municipalityId,
        string $boardCode,
        string $mlsNumber,
        CarbonImmutable $syncedAt,
    ): void {
        $standardStatus = Arr::get($raw, 'StandardStatus');
        $contractStatus = Arr::get($raw, 'ContractStatus');
        $availability = (is_string($standardStatus) && strtolower($standardStatus) === 'active')
            ? 'Available'
            : ((is_string($contractStatus) && strtolower($contractStatus) === 'available') ? 'Available' : 'Unavailable');

        $publicRemarks = Arr::get($raw, 'PublicRemarks');
        $province = Arr::get($raw, 'StateOrProvince') ?: 'ON';

        $listing->fill(array_filter([
            'listing_key' => $key,
            'municipality_id' => $municipalityId,
            'board_code' => $boardCode,
            'mls_number' => $mlsNumber,
            'display_status' => $attrs['status'] ?? null,
            'status_code' => $attrs['status'] ?? null,
            'transaction_type' => (string) (Arr::get($raw, 'TransactionType') ?? 'For Sale'),
            'availability' => $availability,
            'property_type' => $attrs['property_type'] ?? null,
            'property_style' => $attrs['property_sub_type'] ?? null,
            'street_number' => Arr::get($raw, 'StreetNumber'),
            'street_name' => Arr::get($raw, 'StreetName'),
            'street_address' => $attrs['address'] ?? null,
            'public_remarks' => is_string($publicRemarks) ? (string) $publicRemarks : '',
            'unit_number' => Arr::get($raw, 'UnitNumber'),
            'city' => $attrs['city'] ?? null,
            'province' => $province,
            'postal_code' => $attrs['postal_code'] ?? null,
            'list_price' => $attrs['list_price'] ?? null,
            'original_list_price' => Arr::get($raw, 'OriginalListPrice'),
            'bedrooms' => Arr::get($raw, 'BedroomsTotal'),
            'bathrooms' => Arr::get($raw, 'BathroomsTotalInteger'),
            'days_on_market' => Arr::get($raw, 'DaysOnMarket'),
            'listed_at' => $this->resolveListedAt($raw, $syncedAt),
            'modified_at' => $attrs['modified_at'] ?? null,
            'payload' => $raw,
        ], fn ($v) => $v !== null));
    }

    /**
     * Apply source with priority (IDX > VOW).
     *
     * @param  'idx'|'vow'  $incomingSlug
     */
    private function applySourceWithPriority(Listing $listing, string $incomingSlug): void
    {
        $sourceName = $incomingSlug === 'idx' ? 'IDX (PropTx)' : 'VOW (PropTx)';

        $incomingSource = Source::query()->firstOrCreate([
            'slug' => $incomingSlug,
        ], [
            'name' => $sourceName,
            'type' => 'PROP_TX',
        ]);

        $currentSource = $listing->source_id ? Source::find($listing->source_id) : null;
        $currentSlug = $currentSource?->slug;

        $priority = fn (?string $slug): int => match ($slug) {
            'idx' => 2,
            'vow' => 1,
            default => 0,
        };

        if ($listing->source_id === null || $priority($incomingSource->slug) > $priority($currentSlug)) {
            $listing->source_id = $incomingSource->id;
        }
    }

    /**
     * Save listing, handling duplicate entry conflicts.
     */
    private function saveListing(Listing $listing, string $boardCode, string $mlsNumber): void
    {
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
                    $conflict->save();
                } else {
                    throw $e;
                }
            } else {
                throw $e;
            }
        }
    }

    /**
     * Resolve listed_at date from raw API data.
     *
     * @param  array<string, mixed>  $raw
     */
    private function resolveListedAt(array $raw, CarbonImmutable $reference): ?CarbonImmutable
    {
        $candidates = [
            Arr::get($raw, 'ListingContractDate'),
            Arr::get($raw, 'OriginalEntryTimestamp'),
            Arr::get($raw, 'OnMarketDate'),
            Arr::get($raw, 'ListDate'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            $parsed = ListingDateResolver::parse($candidate);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return ListingDateResolver::fromDaysOnMarket(Arr::get($raw, 'DaysOnMarket'), $reference);
    }
}
