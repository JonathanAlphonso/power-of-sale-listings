<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Models\Source;
use App\Services\Idx\ListingUpserter;
use App\Services\Idx\RequestFactory;
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

    public function handle(ListingUpserter $upserter): void
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

        /** @var RequestFactory $factory */
        $factory = app(RequestFactory::class);

        $idxCount = 0;
        $vowCount = 0;

        $idxExpected = $this->getRecentIdxCount($factory, $windowStart);
        $vowExpected = $this->getRecentVowCount($factory, $windowStart);

        try {
            $idxCount = $this->importRecentIdx($upserter, $factory, $windowStart, $idxExpected);
        } catch (\Throwable $e) {
            logger()->error('import_recent.idx_failed', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]);
        }

        try {
            $vowCount = $this->importRecentVow($upserter, $factory, $windowStart, $vowExpected);
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

    private function importRecentIdx(ListingUpserter $upserter, RequestFactory $factory, CarbonImmutable $windowStart, ?int $expectedCount = null): int
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
                    $upserter->upsert($raw, $windowStart, 'idx', requirePowerOfSale: false);
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

    private function importRecentVow(ListingUpserter $upserter, RequestFactory $factory, CarbonImmutable $windowStart, ?int $expectedCount = null): int
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
                    $upserter->upsert($raw, $windowStart, 'vow', requirePowerOfSale: false);
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

        return ['items' => $items, 'next' => null];
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

        return ['items' => $items, 'next' => null];
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
}
