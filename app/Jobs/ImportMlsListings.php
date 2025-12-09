<?php

namespace App\Jobs;

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

    public function handle(ListingUpserter $upserter): void
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
                        $upserter->upsert($raw, $syncedAt, 'idx', requirePowerOfSale: false);
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
}
