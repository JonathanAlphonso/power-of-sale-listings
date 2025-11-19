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
use App\Support\ResoSelects;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class ImportRecentListings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;

    public function __construct(public int $pageSize = 500, public int $maxPages = 200, public ?string $windowStartIso = null) {}

    /**
     * @return array<int, mixed>
     */
    public function middleware(): array
    {
        return [
            (new \Illuminate\Queue\Middleware\WithoutOverlapping('recent-import-all'))->expireAfter($this->timeout),
        ];
    }

    public function handle(IdxClient $idx): void
    {
        $windowStart = $this->windowStartIso !== null
            ? CarbonImmutable::parse($this->windowStartIso)->utc()
            : CarbonImmutable::now('UTC')->subDay();

        $idxSourceId = Source::query()->where('slug', 'idx')->value('id');
        $vowSourceId = Source::query()->where('slug', 'vow')->value('id');

        $beforeIdxDb = $idxSourceId !== null
            ? Listing::query()
                ->where('source_id', $idxSourceId)
                ->where('transaction_type', 'For Sale')
                ->count()
            : null;
        $beforeVowDb = $vowSourceId !== null
            ? Listing::query()
                ->where('source_id', $vowSourceId)
                ->where('transaction_type', 'For Sale')
                ->count()
            : null;

        logger()->info('import_recent.started', [
            'page_size' => $this->pageSize,
            'max_pages' => $this->maxPages,
            'window_start' => $windowStart->toIso8601String(),
            'idx_db_before' => $beforeIdxDb,
            'vow_db_before' => $beforeVowDb,
        ]);

        /** @var ListingTransformer $transformer */
        $transformer = app(ListingTransformer::class);
        /** @var RequestFactory $factory */
        $factory = app(RequestFactory::class);

        $idxCount = 0;
        $vowCount = 0;

        $idxExpected = $this->getRecentIdxCount($factory, $windowStart);
        $vowExpected = $this->getRecentVowCount($factory, $windowStart);

        try {
            $idxCount = $this->importRecentIdx($idx, $transformer, $factory, $windowStart, $idxExpected);
        } catch (\Throwable $e) {
            logger()->error('import_recent.idx_failed', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]);
        }

        try {
            $vowCount = $this->importRecentVow($idx, $transformer, $factory, $windowStart, $vowExpected);
        } catch (\Throwable $e) {
            logger()->error('import_recent.vow_failed', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]);
        }

        $idxDbAfter = $idxSourceId !== null
            ? Listing::query()
                ->where('source_id', $idxSourceId)
                ->where('transaction_type', 'For Sale')
                ->count()
            : null;
        $vowDbAfter = $vowSourceId !== null
            ? Listing::query()
                ->where('source_id', $vowSourceId)
                ->where('transaction_type', 'For Sale')
                ->count()
            : null;

        $summary = [
            'idx_records' => $idxCount,
            'vow_records' => $vowCount,
            'total_records' => $idxCount + $vowCount,
            'window_start' => $windowStart->toIso8601String(),
            'idx_expected' => $idxExpected,
            'vow_expected' => $vowExpected,
            'idx_db_before' => $beforeIdxDb,
            'vow_db_before' => $beforeVowDb,
            'idx_db_after' => $idxDbAfter,
            'vow_db_after' => $vowDbAfter,
            'finished_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];

        logger()->info('import_recent.completed', $summary);

        Cache::put('idx.import.recent', $summary, now()->addMinutes(60));
    }

    private function importRecentIdx(IdxClient $idx, ListingTransformer $transformer, RequestFactory $factory, CarbonImmutable $windowStart, ?int $expectedCount = null): int
    {
        $top = max(1, min($this->pageSize, 100));
        $pages = 0;
        $processed = 0;
        $cursorTimestamp = $windowStart->utc();
        $cursorKey = '0';

        do {
            $pages++;

            logger()->info('import_recent.idx.fetching_page', ['page' => $pages]);

            $page = $this->fetchRecentIdxPage($factory, $top, $cursorTimestamp, $cursorKey);
            $batch = $page['items'];

            $first = $batch[0] ?? null;
            $last = $batch !== [] ? $batch[array_key_last($batch)] : null;

            logger()->info('import_recent.idx.page_fetched', [
                'page' => $pages,
                'records' => count($batch),
                'has_next' => false,
                'cursor_ts_before' => $cursorTimestamp->toIso8601String(),
                'cursor_key_before' => $cursorKey,
                'expected' => $expectedCount,
                'first_ts' => is_array($first) ? (Arr::get($first, 'ModificationTimestamp') ?? Arr::get($first, 'OriginalEntryTimestamp')) : null,
                'first_key' => is_array($first) ? Arr::get($first, 'ListingKey') : null,
                'last_ts' => is_array($last) ? (Arr::get($last, 'ModificationTimestamp') ?? Arr::get($last, 'OriginalEntryTimestamp')) : null,
                'last_key' => is_array($last) ? Arr::get($last, 'ListingKey') : null,
            ]);

            if ($batch === []) {
                logger()->info('import_recent.idx.empty_batch', ['page' => $pages]);

                break;
            }

            foreach ($batch as $raw) {
                if (! is_array($raw)) {
                    continue;
                }

                try {
                    $this->upsertIdxListingFromRaw($idx, $transformer, $raw, $windowStart);
                    $processed++;

                    $tsStr = Arr::get($raw, 'ModificationTimestamp') ?? Arr::get($raw, 'OriginalEntryTimestamp');
                    $key = Arr::get($raw, 'ListingKey');
                    if (is_string($tsStr) && $tsStr !== '' && is_string($key) && $key !== '') {
                        try {
                            $candidate = CarbonImmutable::parse($tsStr)->utc();
                            if ($candidate->lt($cursorTimestamp)) {
                                $candidate = $cursorTimestamp;
                            }
                            $cursorTimestamp = $candidate;
                            $cursorKey = $key;
                        } catch (\Throwable) {
                        }
                    }
                } catch (\Throwable $e) {
                    logger()->warning('import_recent.idx.record_failed', [
                        'listing_key' => Arr::get($raw, 'ListingKey'),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } while (
            count($batch) === $top
            && $pages < $this->maxPages
            && ($expectedCount === null || $processed < $expectedCount)
        );

        logger()->info('import_recent.idx.completed', [
            'total_records' => $processed,
            'total_pages' => $pages,
        ]);

        return $processed;
    }

    private function importRecentVow(IdxClient $idx, ListingTransformer $transformer, RequestFactory $factory, CarbonImmutable $windowStart, ?int $expectedCount = null): int
    {
        $top = max(1, min($this->pageSize, 100));
        $pages = 0;
        $processed = 0;
        $cursorTimestamp = $windowStart->utc();
        $cursorKey = '0';

        do {
            $pages++;

            logger()->info('import_recent.vow.fetching_page', ['page' => $pages]);

            $page = $this->fetchRecentVowPage($factory, $top, $cursorTimestamp, $cursorKey);
            $batch = $page['items'];

            $first = $batch[0] ?? null;
            $last = $batch !== [] ? $batch[array_key_last($batch)] : null;

            logger()->info('import_recent.vow.page_fetched', [
                'page' => $pages,
                'records' => count($batch),
                'has_next' => false,
                'cursor_ts_before' => $cursorTimestamp->toIso8601String(),
                'cursor_key_before' => $cursorKey,
                'expected' => $expectedCount,
                'first_ts' => is_array($first) ? (Arr::get($first, 'ModificationTimestamp') ?? Arr::get($first, 'OriginalEntryTimestamp')) : null,
                'first_key' => is_array($first) ? Arr::get($first, 'ListingKey') : null,
                'last_ts' => is_array($last) ? (Arr::get($last, 'ModificationTimestamp') ?? Arr::get($last, 'OriginalEntryTimestamp')) : null,
                'last_key' => is_array($last) ? Arr::get($last, 'ListingKey') : null,
            ]);

            if ($batch === []) {
                logger()->info('import_recent.vow.empty_batch', ['page' => $pages]);

                break;
            }

            foreach ($batch as $raw) {
                if (! is_array($raw)) {
                    continue;
                }

                try {
                    $this->upsertVowListingFromRaw($idx, $transformer, $raw, $windowStart);
                    $processed++;

                    $tsStr = Arr::get($raw, 'ModificationTimestamp') ?? Arr::get($raw, 'OriginalEntryTimestamp');
                    $key = Arr::get($raw, 'ListingKey');
                    if (is_string($tsStr) && $tsStr !== '' && is_string($key) && $key !== '') {
                        try {
                            $candidate = CarbonImmutable::parse($tsStr)->utc();
                            if ($candidate->lt($cursorTimestamp)) {
                                $candidate = $cursorTimestamp;
                            }
                            $cursorTimestamp = $candidate;
                            $cursorKey = $key;
                        } catch (\Throwable) {
                        }
                    }
                } catch (\Throwable $e) {
                    logger()->warning('import_recent.vow.record_failed', [
                        'listing_key' => Arr::get($raw, 'ListingKey'),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } while (
            count($batch) === $top
            && $pages < $this->maxPages
            && ($expectedCount === null || $processed < $expectedCount)
        );

        logger()->info('import_recent.vow.completed', [
            'total_records' => $processed,
            'total_pages' => $pages,
        ]);

        return $processed;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, next: ?string}
     */
    private function fetchRecentIdxPage(RequestFactory $factory, int $top, CarbonImmutable $cursorTimestamp, string $cursorKey): array
    {
        $select = ResoSelects::propertyPowerOfSaleImport();
        $ts = $cursorTimestamp->utc()->toIso8601String();
        $escapedKey = str_replace("'", "''", $cursorKey === '' ? '0' : $cursorKey);
        $baseFilter = "TransactionType eq 'For Sale'";
        $cursorFilter = sprintf(
            'ModificationTimestamp gt %s or (ModificationTimestamp eq %s and ListingKey gt \'%s\')',
            $ts,
            $ts,
            $escapedKey,
        );
        $filter = $baseFilter.' and '.$cursorFilter;

        $request = $factory->idxProperty(preferMaxPage: true);

        $response = $request->get('Property', [
            '$select' => $select,
            '$filter' => $filter,
            '$orderby' => 'ModificationTimestamp,ListingKey',
            '$top' => $top,
        ]);

        if ($response->failed()) {
            try {
                logger()->warning('import_recent.idx.page_failed', ['status' => $response->status()]);
            } catch (\Throwable) {
            }

            return ['items' => [], 'next' => null];
        }

        $items = $response->json('value');
        $root = $response->json();

        try {
            $count = is_array($items) ? count($items) : 0;
            logger()->info('import_recent.idx.page_ok', [
                'status' => $response->status(),
                'count' => $count,
                'has_next' => false,
            ]);
        } catch (\Throwable) {
        }

        $items = is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
        $next = null;

        return ['items' => $items, 'next' => $next];
    }

    private function getRecentIdxCount(RequestFactory $factory, CarbonImmutable $windowStart): ?int
    {
        $ts = $windowStart->utc()->toIso8601String();

        $request = $factory->idxProperty(preferMaxPage: true);

        $response = $request->get('Property', [
            '$filter' => sprintf("TransactionType eq 'For Sale' and ModificationTimestamp ge %s", $ts),
            '$orderby' => 'ModificationTimestamp,ListingKey',
            '$top' => 0,
            '$count' => 'true',
        ]);

        if ($response->failed()) {
            try {
                logger()->warning('import_recent.idx.count_failed', ['status' => $response->status()]);
            } catch (\Throwable) {
            }

            return null;
        }

        $data = $response->json();

        if (! is_array($data)) {
            return null;
        }

        $count = $data['@odata.count'] ?? null;

        if (is_int($count)) {
            return $count;
        }

        if (is_numeric($count)) {
            return (int) $count;
        }

        return null;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, next: ?string}
     */
    private function fetchRecentVowPage(RequestFactory $factory, int $top, CarbonImmutable $cursorTimestamp, string $cursorKey): array
    {
        $select = ResoSelects::propertyPowerOfSaleImport();
        $ts = $cursorTimestamp->utc()->toIso8601String();
        $escapedKey = str_replace("'", "''", $cursorKey === '' ? '0' : $cursorKey);
        $baseFilter = "TransactionType eq 'For Sale'";
        $cursorFilter = sprintf(
            'ModificationTimestamp gt %s or (ModificationTimestamp eq %s and ListingKey gt \'%s\')',
            $ts,
            $ts,
            $escapedKey,
        );
        $filter = $baseFilter.' and '.$cursorFilter;

        $request = $factory->vowProperty(preferMaxPage: true);

        $response = $request->get('Property', [
            '$select' => $select,
            '$filter' => $filter,
            '$orderby' => 'ModificationTimestamp,ListingKey',
            '$top' => $top,
        ]);

        if ($response->failed()) {
            try {
                logger()->warning('import_recent.vow.page_failed', ['status' => $response->status()]);
            } catch (\Throwable) {
            }

            return ['items' => [], 'next' => null];
        }

        $items = $response->json('value');
        $root = $response->json();

        try {
            $count = is_array($items) ? count($items) : 0;
            logger()->info('import_recent.vow.page_ok', [
                'status' => $response->status(),
                'count' => $count,
                'has_next' => false,
            ]);
        } catch (\Throwable) {
        }

        $items = is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
        $next = null;

        return ['items' => $items, 'next' => $next];
    }

    private function getRecentVowCount(RequestFactory $factory, CarbonImmutable $windowStart): ?int
    {
        $ts = $windowStart->utc()->toIso8601String();

        $request = $factory->vowProperty(preferMaxPage: true);

        $response = $request->get('Property', [
            '$filter' => sprintf("TransactionType eq 'For Sale' and ModificationTimestamp ge %s", $ts),
            '$orderby' => 'ModificationTimestamp,ListingKey',
            '$top' => 0,
            '$count' => 'true',
        ]);

        if ($response->failed()) {
            try {
                logger()->warning('import_recent.vow.count_failed', ['status' => $response->status()]);
            } catch (\Throwable) {
            }

            return null;
        }

        $data = $response->json();

        if (! is_array($data)) {
            return null;
        }

        $count = $data['@odata.count'] ?? null;

        if (is_int($count)) {
            return $count;
        }

        if (is_numeric($count)) {
            return (int) $count;
        }

        return null;
    }

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

    private function upsertIdxListingFromRaw(IdxClient $idx, ListingTransformer $transformer, array $raw, CarbonImmutable $syncedAt): void
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

        \App\Jobs\SyncIdxMediaForListing::dispatch((int) $listing->id, (string) $key)->onQueue('media');
    }

    private function upsertVowListingFromRaw(IdxClient $idx, ListingTransformer $transformer, array $raw, CarbonImmutable $syncedAt): void
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

        $dirtyKeys = array_keys($listing->getDirty());
        $effectiveDirty = array_values(array_diff($dirtyKeys, ['payload']));
        $statusChanged = in_array('status_code', $dirtyKeys, true) || in_array('display_status', $dirtyKeys, true);

        if ($effectiveDirty === []) {
            return;
        }

        $listing->save();

        if ($statusChanged) {
            $listing->recordStatusHistory($listing->status_code, $listing->display_status, $raw, $listing->modified_at ?? now());
        }

        \App\Jobs\SyncIdxMediaForListing::dispatch((int) $listing->id, (string) $key)->onQueue('media');
    }
}
