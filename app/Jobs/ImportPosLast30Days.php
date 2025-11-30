<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Models\Municipality;
use App\Models\Source;
use App\Services\Idx\IdxClient;
use App\Services\Idx\ListingTransformer;
use App\Services\Idx\RequestFactory;
use App\Support\BoardCode;
use App\Support\ListingDateResolver;
use App\Support\ResoFilters;
use App\Support\ResoSelects;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Import Power of Sale listings modified in the last 30 days.
 *
 * This job:
 * 1. Queries the IDX API for "For Sale" listings modified in the last 30 days
 * 2. Filters each listing for POS keywords in PublicRemarks
 * 3. Upserts matching listings into the database
 * 4. Syncs media for each imported listing
 */
class ImportPosLast30Days implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes

    public function __construct(
        public int $pageSize = 100,
        public int $maxPages = 500,
        public int $days = 30,
        public string $feed = 'idx', // 'idx' or 'vow'
    ) {}

    /**
     * Prevent overlapping imports.
     *
     * @return array<int, mixed>
     */
    public function middleware(): array
    {
        return [
            (new \Illuminate\Queue\Middleware\WithoutOverlapping("pos-import-30d-{$this->feed}"))->expireAfter($this->timeout),
        ];
    }

    public function handle(IdxClient $idx): void
    {
        $windowStart = CarbonImmutable::now('UTC')->subDays($this->days);

        logger()->info("import_pos_30d.{$this->feed}.started", [
            'page_size' => $this->pageSize,
            'max_pages' => $this->maxPages,
            'days' => $this->days,
            'window_start' => $windowStart->toIso8601String(),
        ]);

        Cache::put('idx.import.pos', [
            'status' => 'running',
            'items_total' => 0,
            'pages' => 0,
            'started_at' => now()->toISOString(),
            'feed' => $this->feed,
            'window_start' => $windowStart->toIso8601String(),
        ], now()->addMinutes(60));

        /** @var ListingTransformer $transformer */
        $transformer = app(ListingTransformer::class);

        $top = max(1, min($this->pageSize, 100));
        $pages = 0;
        $next = null;
        $processed = 0;
        $posMatched = 0;
        $syncedAt = now()->toImmutable();

        // Cursor tracking for pagination (since API may not return @odata.nextLink)
        $lastTimestamp = $windowStart;
        $lastKey = '0';

        do {
            $pages++;
            logger()->info("import_pos_30d.{$this->feed}.fetching_page", ['page' => $pages]);

            $page = $this->fetchPage($top, $next, $windowStart, $lastTimestamp, $lastKey);
            $batch = $page['items'];
            $next = $page['next'];

            logger()->info("import_pos_30d.{$this->feed}.page_fetched", [
                'page' => $pages,
                'records' => count($batch),
                'has_next' => $next !== null,
            ]);

            if ($batch === []) {
                logger()->info("import_pos_30d.{$this->feed}.empty_batch", ['page' => $pages]);
                break;
            }

            foreach ($batch as $raw) {
                if (! is_array($raw)) {
                    continue;
                }

                $processed++;

                // Check if this listing contains POS keywords
                $remarks = Arr::get($raw, 'PublicRemarks');
                if (! is_string($remarks) || ! ResoFilters::isPowerOfSaleRemarks($remarks)) {
                    continue;
                }

                try {
                    $this->upsertListing($transformer, $raw, $syncedAt);
                    $posMatched++;
                } catch (\Throwable $e) {
                    logger()->warning("import_pos_30d.{$this->feed}.record_failed", [
                        'listing_key' => Arr::get($raw, 'ListingKey'),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Update cursor from the last item in batch for next iteration
            if ($batch !== []) {
                $tail = end($batch);
                if (is_array($tail)) {
                    $newTsStr = Arr::get($tail, 'ModificationTimestamp');
                    $newKey = Arr::get($tail, 'ListingKey');
                    if (is_string($newTsStr) && is_string($newKey) && $newTsStr !== '' && $newKey !== '') {
                        try {
                            $candidate = CarbonImmutable::parse($newTsStr)->utc();
                            $nowUtc = CarbonImmutable::now('UTC');
                            if ($candidate->isFuture()) {
                                $candidate = $nowUtc;
                            }
                            if ($candidate->lt($lastTimestamp)) {
                                $candidate = $lastTimestamp;
                            }
                            $lastTimestamp = $candidate;
                            $lastKey = $newKey;
                        } catch (\Throwable $e) {
                            logger()->warning("import_pos_30d.{$this->feed}.cursor_update_failed", [
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }

            Cache::put('idx.import.pos', [
                'status' => 'running',
                'items_total' => $posMatched,
                'items_scanned' => $processed,
                'pages' => $pages,
                'last_at' => now()->toISOString(),
                'feed' => $this->feed,
            ], now()->addMinutes(60));

        } while ((count($batch) === $top || $next !== null) && $pages < $this->maxPages);

        logger()->info("import_pos_30d.{$this->feed}.completed", [
            'total_scanned' => $processed,
            'total_pos_matched' => $posMatched,
            'total_pages' => $pages,
        ]);

        Cache::put('idx.import.pos', [
            'status' => 'completed',
            'items_total' => $posMatched,
            'items_scanned' => $processed,
            'pages' => $pages,
            'finished_at' => now()->toISOString(),
            'feed' => $this->feed,
        ], now()->addMinutes(60));
    }

    /**
     * Fetch a page of listings modified since the window start.
     *
     * @return array{items: array<int, array<string, mixed>>, next: ?string}
     */
    protected function fetchPage(int $top, ?string $nextUrl, CarbonImmutable $windowStart, CarbonImmutable $lastTimestamp, string $lastKey): array
    {
        $select = ResoSelects::propertyPowerOfSaleImport();

        // Build filter with cursor for proper pagination
        $baseFilter = sprintf(
            "TransactionType eq 'For Sale' and ModificationTimestamp ge %s",
            $windowStart->toIso8601String()
        );
        $filter = $this->composeCursorFilter($baseFilter, $lastTimestamp, $lastKey);

        /** @var RequestFactory $factory */
        $factory = app(RequestFactory::class);
        $request = $this->feed === 'vow'
            ? $factory->vowProperty(preferMaxPage: true)
            : $factory->idxProperty(preferMaxPage: true);

        $baseUri = $this->feed === 'vow'
            ? (string) config('services.vow.base_uri', '')
            : (string) config('services.idx.base_uri', '');

        $response = $nextUrl !== null
            ? (function () use ($request, $nextUrl, $baseUri) {
                $next = (string) $nextUrl;
                $relative = $next;

                if (str_starts_with($next, 'http')) {
                    $path = (string) parse_url($next, PHP_URL_PATH);
                    $query = (string) parse_url($next, PHP_URL_QUERY);
                    $relative = ltrim($path, '/') . ($query !== '' ? ('?' . $query) : '');
                }

                return $request
                    ->baseUrl(rtrim($baseUri, '/'))
                    ->withHeaders(['Prefer' => 'odata.maxpagesize=500'])
                    ->get($relative);
            })()
            : $request->get('Property', [
                '$select' => $select,
                '$filter' => $filter,
                '$orderby' => 'ModificationTimestamp,ListingKey',
                '$top' => $top,
            ]);

        if ($response->failed()) {
            logger()->warning("import_pos_30d.{$this->feed}.page_failed", [
                'status' => $response->status(),
            ]);

            return ['items' => [], 'next' => null];
        }

        $items = $response->json('value');
        $root = $response->json();

        logger()->info("import_pos_30d.{$this->feed}.page_ok", [
            'status' => $response->status(),
            'count' => is_array($items) ? count($items) : 0,
            'has_next' => is_array($root) && isset($root['@odata.nextLink']),
        ]);

        $items = is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
        $next = (is_array($root) && isset($root['@odata.nextLink']) && is_string($root['@odata.nextLink']) && $root['@odata.nextLink'] !== '')
            ? $root['@odata.nextLink']
            : null;

        return ['items' => $items, 'next' => $next];
    }

    protected function upsertListing(ListingTransformer $transformer, array $raw, CarbonImmutable $syncedAt): void
    {
        $key = Arr::get($raw, 'ListingKey');
        if (! is_string($key) || $key === '') {
            return;
        }

        logger()->info("import_pos_30d.{$this->feed}.pos_flagged", [
            'listing_key' => $key,
            'originating_system' => Arr::get($raw, 'OriginatingSystemName') ?? Arr::get($raw, 'SourceSystemName'),
            'transaction_type' => Arr::get($raw, 'TransactionType'),
            'remarks_preview' => \Illuminate\Support\Str::limit(Arr::get($raw, 'PublicRemarks') ?? '', 160),
        ]);

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
        $mlsNumber = Arr::get($raw, 'ListingId') ?? $key;

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

        // Apply source with priority (IDX > VOW)
        $sourceSlug = $this->feed === 'vow' ? 'vow' : 'idx';
        $sourceName = $this->feed === 'vow' ? 'VOW (PropTx)' : 'IDX (PropTx)';

        $incomingSource = Source::query()->firstOrCreate([
            'slug' => $sourceSlug,
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

        // Sync media
        SyncIdxMediaForListing::dispatch((int) $listing->id, (string) $key)->onQueue('media');
    }

    protected function resolveListedAt(array $raw, CarbonImmutable $reference): ?CarbonImmutable
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

    /**
     * Build a cursor-based filter for pagination.
     *
     * Uses ModificationTimestamp + ListingKey to ensure proper pagination
     * even when the API doesn't return @odata.nextLink.
     */
    private function composeCursorFilter(string $baseFilter, CarbonImmutable $lastTimestamp, string $lastKey): string
    {
        $tsObj = $lastTimestamp->utc();
        $nowUtc = CarbonImmutable::now('UTC');

        // Never compose a filter with a future timestamp
        if ($tsObj->isFuture()) {
            $tsObj = $nowUtc;
        }

        $ts = $tsObj->toIso8601String();
        $escapedKey = str_replace("'", "''", $lastKey);

        $cursor = sprintf(
            '(ModificationTimestamp gt %s or (ModificationTimestamp eq %s and ListingKey gt \'%s\'))',
            $ts,
            $ts,
            $escapedKey,
        );

        return trim($baseFilter) !== '' ? ($baseFilter . ' and ' . $cursor) : $cursor;
    }
}
