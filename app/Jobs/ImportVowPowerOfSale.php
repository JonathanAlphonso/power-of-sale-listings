<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Models\Municipality;
use App\Models\ReplicationCursor;
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

class ImportVowPowerOfSale implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200; // 20 minutes

    public function __construct(public int $pageSize = 500, public int $maxPages = 200) {}

    /**
     * Prevent overlapping imports to avoid heavy duplicate work.
     *
     * @return array<int, mixed>
     */
    public function middleware(): array
    {
        return [
            (new \Illuminate\Queue\Middleware\WithoutOverlapping('pos-import-vow'))->expireAfter($this->timeout),
        ];
    }

    public function handle(IdxClient $idx): void
    {
        logger()->info('import_vow_pos.started', [
            'page_size' => $this->pageSize,
            'max_pages' => $this->maxPages,
        ]);

        /** @var ListingTransformer $transformer */
        $transformer = app(ListingTransformer::class);
        $top = max(1, min($this->pageSize, 100));
        $pages = 0;
        $next = null;
        $processed = 0;

        // Replication cursor: last timestamp + last key
        $channel = 'vow.property.pos';
        /** @var ReplicationCursor $cursor */
        $cursor = ReplicationCursor::query()->firstOrCreate(['channel' => $channel], [
            'last_timestamp' => CarbonImmutable::create(1970, 1, 1, 0, 0, 0, 'UTC'),
            'last_key' => '0',
        ]);
        $lastTs = $cursor->last_timestamp instanceof \DateTimeInterface
            ? CarbonImmutable::instance($cursor->last_timestamp)
            : CarbonImmutable::create(1970, 1, 1, 0, 0, 0, 'UTC');
        $lastKey = is_string($cursor->last_key) && $cursor->last_key !== '' ? $cursor->last_key : '0';

        logger()->info('import_vow_pos.cursor', [
            'last_timestamp' => $lastTs->toIso8601String(),
            'last_key' => $lastKey,
        ]);

        $syncedAt = now()->toImmutable();

        do {
            $pages++;
            logger()->info('import_vow_pos.fetching_page', ['page' => $pages]);

            $page = $this->fetchPowerOfSalePage($top, $next, $lastTs, $lastKey);
            $batch = $page['items'];
            $next = $page['next'];

            logger()->info('import_vow_pos.page_fetched', [
                'page' => $pages,
                'records' => count($batch),
                'has_next' => $next !== null,
            ]);

            if ($batch === []) {
                logger()->info('import_vow_pos.empty_batch', ['page' => $pages]);
                break;
            }

            foreach ($batch as $raw) {
                if (! is_array($raw)) {
                    continue;
                }

                try {
                    $this->upsertListingFromRaw($idx, $transformer, $raw, $syncedAt);
                    $processed++;
                } catch (\Throwable $e) {
                    logger()->warning('import_vow_pos.record_failed', [
                        'listing_key' => Arr::get($raw, 'ListingKey'),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Update replication cursor based on last record in this batch
            $tail = end($batch);
            if (is_array($tail)) {
                $newTsStr = Arr::get($tail, 'ModificationTimestamp');
                $newKey = Arr::get($tail, 'ListingKey');
                if (is_string($newTsStr) && is_string($newKey) && $newTsStr !== '' && $newKey !== '') {
                    try {
                        $candidate = CarbonImmutable::parse($newTsStr)->utc();
                        $nowUtc = CarbonImmutable::now('UTC');
                        if ($candidate->isFuture()) {
                            $candidate = $nowUtc; // clamp to now to avoid future cursor
                        }
                        if ($candidate->lt($lastTs)) {
                            $candidate = $lastTs; // monotonic non-decreasing
                        }

                        $lastTs = $candidate;
                        $lastKey = $newKey;
                        $cursor->forceFill([
                            'last_timestamp' => $lastTs,
                            'last_key' => $lastKey,
                        ])->save();

                        logger()->info('import_vow_pos.cursor_updated', [
                            'last_timestamp' => $lastTs->toIso8601String(),
                            'last_key' => $lastKey,
                        ]);
                    } catch (\Throwable $e) {
                        logger()->warning('import_vow_pos.cursor_update_failed', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        } while ((count($batch) === $top || $next !== null) && $pages < $this->maxPages);

        logger()->info('import_vow_pos.completed', [
            'total_records' => $processed,
            'total_pages' => $pages,
        ]);
    }

    /**
     * Fetch a page of results. If nextUrl is provided, it is used directly (relative to base URI).
     *
     * @return array{items: array<int, array<string, mixed>>, next: ?string}
     */
    protected function fetchPowerOfSalePage(int $top, ?string $nextUrl = null, ?CarbonImmutable $lastTimestamp = null, ?string $lastKey = null): array
    {
        // Base query components
        $select = ResoSelects::propertyPowerOfSaleImport();

        $filter = ResoFilters::powerOfSale();
        /** @var RequestFactory $factory */
        $factory = app(RequestFactory::class);
        $request = $factory->vowProperty(preferMaxPage: true);

        $response = $nextUrl !== null
            ? (function () use ($request, $nextUrl) {
                $next = (string) $nextUrl;

                $relative = $next;
                if (str_starts_with($next, 'http')) {
                    $path = (string) parse_url($next, PHP_URL_PATH);
                    $query = (string) parse_url($next, PHP_URL_QUERY);
                    $relative = ltrim($path, '/').($query !== '' ? ('?'.$query) : '');
                }

                return $request
                    ->baseUrl(rtrim((string) config('services.vow.base_uri', ''), '/'))
                    ->withHeaders(['Prefer' => 'odata.maxpagesize=500'])
                    ->get($relative);
            })()
            : $request
                ->get('Property', [
                    '$select' => $select,
                    '$filter' => $this->composeCursorFilter($filter, $lastTimestamp, (string) ($lastKey ?: '0')),
                    '$orderby' => 'ModificationTimestamp,ListingKey',
                    '$top' => $top,
                ]);

        if ($response->failed()) {
            try {
                logger()->warning('VOW import page failed', ['status' => $response->status()]);
            } catch (\Throwable) {
            }

            return ['items' => [], 'next' => null];
        }

        $items = $response->json('value');
        $root = $response->json();

        // Safety: if first page returns zero with the cursor filter, retry once without it.
        if ($nextUrl === null && (! is_array($items) || count($items) === 0)) {
            try {
                logger()->info('VOW import fallback: retrying without cursor filter');
            } catch (\Throwable) {
            }
            $fallback = $request->get('Property', [
                '$select' => $select,
                '$filter' => $filter,
                '$orderby' => 'ModificationTimestamp,ListingKey',
                '$top' => $top,
            ]);
            if ($fallback->successful()) {
                $items = $fallback->json('value');
                $root = $fallback->json();
                try {
                    $count = is_array($items) ? count($items) : 0;
                    logger()->info('VOW import fallback page ok', ['count' => $count]);
                } catch (\Throwable) {
                }
            }
        }

        try {
            $count = is_array($items) ? count($items) : 0;
            logger()->info('VOW import page ok', [
                'status' => $response->status(),
                'count' => $count,
                'has_next' => is_array($root) && isset($root['@odata.nextLink']),
            ]);
        } catch (\Throwable) {
        }

        $items = is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
        $next = (is_array($root) && isset($root['@odata.nextLink']) && is_string($root['@odata.nextLink']) && $root['@odata.nextLink'] !== '')
            ? $root['@odata.nextLink']
            : null;

        return ['items' => $items, 'next' => $next];
    }

    private function composeCursorFilter(string $baseFilter, ?CarbonImmutable $lastTimestamp, string $lastKey): string
    {
        $tsObj = ($lastTimestamp ?? CarbonImmutable::create(1970, 1, 1, 0, 0, 0, 'UTC'))->utc();
        $nowUtc = CarbonImmutable::now('UTC');
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

        return trim($baseFilter) !== '' ? ($baseFilter.' and '.$cursor) : $cursor;
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

    protected function upsertListingFromRaw(IdxClient $idx, ListingTransformer $transformer, array $raw, CarbonImmutable $syncedAt): void
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
            Arr::get($raw, 'OriginatingSystemName') ?? Arr::get($raw, 'SourceSystemName')
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

        // Ensure NOT NULL columns are always populated
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
            'mls_number' => Arr::get($raw, 'ListingId') ?? Arr::get($raw, 'MLSNumber') ?? $key,
            'display_status' => $attrs['status'] ?? null,
            'status_code' => $attrs['status'] ?? null,
            'transaction_type' => (string) (Arr::get($raw, 'TransactionType') ?? 'For Sale'),
            'availability' => $availability,
            'property_type' => $attrs['property_type'] ?? null,
            'property_style' => $attrs['property_sub_type'] ?? null,
            'street_number' => Arr::get($raw, 'StreetNumber'),
            'street_name' => Arr::get($raw, 'StreetName'),
            'street_address' => $attrs['address'] ?? null,
            'public_remarks' => is_string($publicRemarks) ? trim((string) $publicRemarks) : '',
            'public_remarks_full' => is_string($publicRemarks) ? (string) $publicRemarks : '',
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

        // Apply source with priority (IDX > VOW). Incoming here is VOW.
        $incomingSource = Source::query()->firstOrCreate([
            'slug' => 'vow',
        ], [
            'name' => 'VOW (PropTx)',
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

        // Determine if anything actually changed (ignore noisy JSON payload changes)
        $dirtyKeys = array_keys($listing->getDirty());
        $effectiveDirty = array_values(array_diff($dirtyKeys, ['payload']));
        $statusChanged = in_array('status_code', $dirtyKeys, true) || in_array('display_status', $dirtyKeys, true);

        if ($effectiveDirty === []) {
            // Nothing meaningful changed; skip save and history/media work
            return;
        }

        $listing->save();

        // Record status history only when status fields changed
        if ($statusChanged) {
            $listing->recordStatusHistory($listing->status_code, $listing->display_status, $raw, $listing->modified_at ?? now());
        }

        // Also sync media from the Media resource for this listing key.
        \App\Jobs\SyncIdxMediaForListing::dispatch((int) $listing->id, (string) $key)->onQueue('media');
    }

    // Mapping handled by ListingTransformer

    // Board code normalization handled by App\Support\BoardCode
}
