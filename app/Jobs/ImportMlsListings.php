<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Models\Municipality;
use App\Models\Source;
use App\Services\Idx\ListingTransformer;
use App\Services\Idx\RequestFactory;
use App\Support\BoardCode;
use App\Support\ListingDateResolver;
use App\Support\ResoSelects;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class ImportMlsListings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    /**
     * @param  array<int, string>  $mlsNumbers
     */
    public function __construct(
        public array $mlsNumbers,
    ) {}

    /**
     * @return array<int, mixed>
     */
    public function middleware(): array
    {
        return [
            (new \Illuminate\Queue\Middleware\WithoutOverlapping('mls-import'))->expireAfter($this->timeout),
        ];
    }

    public function handle(): void
    {
        // Input is already deduplicated in the controller
        $mlsNumbers = array_values(array_filter($this->mlsNumbers, fn ($v) => is_string($v) && $v !== ''));

        if (empty($mlsNumbers)) {
            logger()->info('import_mls.skipped_empty');

            return;
        }

        logger()->info('import_mls.started', [
            'count' => count($mlsNumbers),
            'first_5' => array_slice($mlsNumbers, 0, 5),
        ]);

        /** @var ListingTransformer $transformer */
        $transformer = app(ListingTransformer::class);
        /** @var RequestFactory $factory */
        $factory = app(RequestFactory::class);

        $imported = 0;
        $failed = 0;
        $notFound = 0;
        $syncedAt = CarbonImmutable::now('UTC');

        // Process in batches of 10 to avoid overly long OData filter strings
        foreach (array_chunk($mlsNumbers, 10) as $batch) {
            try {
                $results = $this->fetchListingsByMls($factory, $batch);

                foreach ($results as $raw) {
                    if (! is_array($raw)) {
                        continue;
                    }

                    try {
                        $this->upsertListingFromRaw($transformer, $raw, $syncedAt);
                        $imported++;
                    } catch (\Throwable $e) {
                        $failed++;
                        logger()->warning('import_mls.record_failed', [
                            'listing_key' => Arr::get($raw, 'ListingKey'),
                            'mls' => Arr::get($raw, 'ListingId'),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Track which MLS IDs were not found (using ListingKey since that's what we filter by)
                $foundMls = array_map(
                    fn ($r) => is_array($r) ? (string) (Arr::get($r, 'ListingKey') ?? '') : '',
                    $results
                );
                $foundMls = array_filter($foundMls);

                foreach ($batch as $requestedMls) {
                    if (! in_array($requestedMls, $foundMls, true)) {
                        $notFound++;
                        logger()->info('import_mls.not_found', ['mls' => $requestedMls]);
                    }
                }
            } catch (\Throwable $e) {
                $failed += count($batch);
                logger()->error('import_mls.batch_failed', [
                    'batch_size' => count($batch),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $summary = [
            'requested' => count($mlsNumbers),
            'imported' => $imported,
            'failed' => $failed,
            'not_found' => $notFound,
            'finished_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];

        logger()->info('import_mls.completed', $summary);

        Cache::put('idx.import.mls', $summary, now()->addMinutes(60));
    }

    /**
     * Fetch listings from IDX by MLS numbers.
     *
     * @param  array<int, string>  $mlsNumbers
     * @return array<int, array<string, mixed>>
     */
    private function fetchListingsByMls(RequestFactory $factory, array $mlsNumbers): array
    {
        if (empty($mlsNumbers)) {
            return [];
        }

        $select = ResoSelects::propertyPowerOfSaleImport();

        // Build OData filter: ListingKey eq 'X123' or ListingKey eq 'X456' ...
        // Note: ListingId is not filterable in the API, but ListingKey is
        $filterParts = array_map(function (string $mls) {
            $escaped = str_replace("'", "''", $mls);

            return "ListingKey eq '{$escaped}'";
        }, $mlsNumbers);

        $filter = implode(' or ', $filterParts);

        $request = $factory->idxProperty(preferMaxPage: true)->timeout(60);

        $response = $request->get('Property', [
            '$select' => $select,
            '$filter' => $filter,
            '$top' => count($mlsNumbers) * 2, // Allow some buffer for potential duplicates
        ]);

        if ($response->failed()) {
            logger()->warning('import_mls.fetch_failed', [
                'status' => $response->status(),
                'count' => count($mlsNumbers),
            ]);

            return [];
        }

        $items = $response->json('value');

        if (! is_array($items)) {
            return [];
        }

        logger()->info('import_mls.fetch_ok', [
            'requested' => count($mlsNumbers),
            'returned' => count($items),
        ]);

        return array_values(array_filter($items, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function upsertListingFromRaw(ListingTransformer $transformer, array $raw, CarbonImmutable $syncedAt): void
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

        $attrs = $transformer->transform($raw);

        $boardCode = BoardCode::fromSystemName(
            Arr::get($raw, 'OriginatingSystemName')
                ?? Arr::get($raw, 'SourceSystemName')
                ?? Arr::get($raw, 'ListAOR')
        );
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

        $listing->listing_key = $key;

        if (method_exists($listing, 'trashed') && $listing->trashed()) {
            $listing->restore();
        }

        $standardStatus = Arr::get($raw, 'StandardStatus');
        $contractStatus = Arr::get($raw, 'ContractStatus');
        $availability = (is_string($standardStatus) && strtolower($standardStatus) === 'active')
            ? 'Available'
            : ((is_string($contractStatus) && strtolower($contractStatus) === 'available') ? 'Available' : 'Unavailable');

        $publicRemarks = Arr::get($raw, 'PublicRemarks');

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

        $incomingSource = Source::query()->firstOrCreate([
            'slug' => 'idx',
        ], [
            'name' => 'IDX (PropTx)',
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

        $dirtyKeys = array_keys($listing->getDirty());
        $effectiveDirty = array_values(array_diff($dirtyKeys, ['payload']));
        $statusChanged = in_array('status_code', $dirtyKeys, true) || in_array('display_status', $dirtyKeys, true);

        if ($effectiveDirty === []) {
            return;
        }

        try {
            $listing->save();
        } catch (\Illuminate\Database\QueryException $e) {
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

        if ($statusChanged) {
            $listing->recordStatusHistory($listing->status_code, $listing->display_status, $raw, $listing->modified_at ?? now());
        }

        SyncIdxMediaForListing::dispatch((int) $listing->id, (string) $key)->onQueue('media');
    }

    /**
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
