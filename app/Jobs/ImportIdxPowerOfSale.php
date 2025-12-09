<?php

namespace App\Jobs;

use App\Models\ReplicationCursor;
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

class ImportIdxPowerOfSale implements ShouldQueue
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
            (new \Illuminate\Queue\Middleware\WithoutOverlapping('pos-import-idx'))->expireAfter($this->timeout),
        ];
    }

    public function handle(ListingUpserter $upserter): void
    {
        logger()->info('import_idx_pos.started', [
            'page_size' => $this->pageSize,
            'max_pages' => $this->maxPages,
        ]);

        $top = max(1, min($this->pageSize, 100));
        $pages = 0;
        $next = null;
        $processed = 0;

        // Replication cursor: last timestamp + last key
        $channel = 'idx.property.pos';
        /** @var ReplicationCursor $cursor */
        $cursor = ReplicationCursor::query()->firstOrCreate(['channel' => $channel], [
            'last_timestamp' => CarbonImmutable::create(1970, 1, 1, 0, 0, 0, 'UTC'),
            'last_key' => '0',
        ]);
        $lastTs = $cursor->last_timestamp instanceof \DateTimeInterface
            ? CarbonImmutable::instance($cursor->last_timestamp)
            : CarbonImmutable::create(1970, 1, 1, 0, 0, 0, 'UTC');
        $lastKey = is_string($cursor->last_key) && $cursor->last_key !== '' ? $cursor->last_key : '0';

        logger()->info('import_idx_pos.cursor', [
            'last_timestamp' => $lastTs->toIso8601String(),
            'last_key' => $lastKey,
        ]);

        Cache::put('idx.import.pos', [
            'status' => 'running',
            'items_total' => 0,
            'pages' => 0,
            'started_at' => now()->toISOString(),
        ], now()->addMinutes(10));

        $syncedAt = now()->toImmutable();

        do {
            $pages++;
            logger()->info('import_idx_pos.fetching_page', ['page' => $pages]);

            $page = $this->fetchPowerOfSalePage($top, $next, $lastTs, $lastKey);
            $batch = $page['items'];
            $next = $page['next'];

            logger()->info('import_idx_pos.page_fetched', [
                'page' => $pages,
                'records' => count($batch),
                'has_next' => $next !== null,
            ]);

            if ($pages === 1) {
                logger()->info('import_idx_pos.initial_page', [
                    'records' => count($batch),
                    'cursor_timestamp' => $lastTs->toIso8601String(),
                    'cursor_key' => $lastKey,
                ]);
            }

            if ($batch === []) {
                logger()->info('import_idx_pos.empty_batch', ['page' => $pages]);
                break;
            }

            foreach ($batch as $raw) {
                if (! is_array($raw)) {
                    continue;
                }

                try {
                    $upserter->upsert($raw, $syncedAt, 'idx', requirePowerOfSale: true);
                    $processed++;
                } catch (\Throwable $e) {
                    logger()->warning('import_idx_pos.record_failed', [
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
                            $candidate = $nowUtc; // clamp: never advance into the future
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

                        logger()->info('import_idx_pos.cursor_updated', [
                            'last_timestamp' => $lastTs->toIso8601String(),
                            'last_key' => $lastKey,
                        ]);
                    } catch (\Throwable $e) {
                        logger()->warning('import_idx_pos.cursor_update_failed', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
            Cache::put('idx.import.pos', [
                'status' => 'running',
                'items_total' => $processed,
                'pages' => $pages,
                'last_at' => now()->toISOString(),
            ], now()->addMinutes(10));
        } while ((count($batch) === $top || $next !== null) && $pages < $this->maxPages);

        logger()->info('import_idx_pos.completed', [
            'total_records' => $processed,
            'total_pages' => $pages,
        ]);

        Cache::put('idx.import.pos', [
            'status' => 'completed',
            'items_total' => $processed,
            'pages' => $pages,
            'finished_at' => now()->toISOString(),
        ], now()->addMinutes(10));
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

        // Remote filter only ensures we receive "For Sale" records; Power-of-Sale
        // detection is performed locally using normalized remarks.
        $baseFilter = "TransactionType eq 'For Sale'";
        /** @var RequestFactory $factory */
        $factory = app(RequestFactory::class);
        $request = $factory->idxProperty(preferMaxPage: true);

        $response = $nextUrl !== null
            ? (function () use ($request, $nextUrl) {
                $next = (string) $nextUrl;

                // Prefer requesting via the relative nextLink against the configured base URL
                // to satisfy fakes that expect an exact relative path match like
                // "Property?$skip=1&$top=1".
                $relative = $next;

                if (str_starts_with($next, 'http')) {
                    $path = (string) parse_url($next, PHP_URL_PATH);
                    $query = (string) parse_url($next, PHP_URL_QUERY);
                    $relative = ltrim($path, '/').($query !== '' ? ('?'.$query) : '');
                }

                return $request
                    ->baseUrl(rtrim((string) config('services.idx.base_uri', ''), '/'))
                    ->withHeaders(['Prefer' => 'odata.maxpagesize=500'])
                    ->get($relative);
            })()
            : $request
                ->get('Property', [
                    '$select' => $select,
                    '$filter' => $this->composeCursorFilter($baseFilter, $lastTimestamp, (string) ($lastKey ?: '0')),
                    '$orderby' => 'ModificationTimestamp,ListingKey',
                    '$top' => $top,
                ]);

        if ($response->failed()) {
            try {
                logger()->warning('IDX import page failed', ['status' => $response->status()]);
            } catch (\Throwable) {
            }

            return ['items' => [], 'next' => null];
        }

        $items = $response->json('value');
        $root = $response->json();

        // Safety: if first page (no nextUrl) returns zero items with the cursor filter,
        // retry once without the cursor portion to ensure we pull available PoS data.
        if ($nextUrl === null && (! is_array($items) || count($items) === 0)) {
            try {
                logger()->info('IDX import fallback: retrying without cursor filter');
            } catch (\Throwable) {
            }
            $fallback = $request->get('Property', [
                '$select' => $select,
                '$filter' => $baseFilter,
                '$orderby' => 'ModificationTimestamp,ListingKey',
                '$top' => $top,
            ]);
            if ($fallback->successful()) {
                $items = $fallback->json('value');
                $root = $fallback->json();
                try {
                    $count = is_array($items) ? count($items) : 0;
                    logger()->info('IDX import fallback page ok', ['count' => $count]);
                } catch (\Throwable) {
                }
            }
        }

        try {
            $count = is_array($items) ? count($items) : 0;
            logger()->info('IDX import page ok', [
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

        return trim($baseFilter) !== '' ? ($baseFilter.' and '.$cursor) : $cursor;
    }
}
