<?php

namespace App\Jobs;

use App\Services\Idx\IdxClient;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Import Power of Sale listings from both IDX and VOW feeds.
 *
 * This job imports listings modified in the last N days (default: 30),
 * filters for POS keywords in PublicRemarks, and syncs media.
 */
class ImportAllPowerOfSaleFeeds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour for both feeds

    public function __construct(
        public int $pageSize = 100,
        public int $maxPages = 500,
        public int $days = 30,
    ) {}

    /**
     * Prevent overlapping full imports to avoid duplicate work.
     *
     * @return array<int, mixed>
     */
    public function middleware(): array
    {
        return [
            (new \Illuminate\Queue\Middleware\WithoutOverlapping('pos-import-all'))->expireAfter($this->timeout),
        ];
    }

    public function handle(IdxClient $idx): void
    {
        $windowStart = CarbonImmutable::now('UTC')->subDays($this->days);

        logger()->info('import_all_pos.started', [
            'page_size' => $this->pageSize,
            'max_pages' => $this->maxPages,
            'days' => $this->days,
            'window_start' => $windowStart->toIso8601String(),
            'timestamp' => now()->toIso8601String(),
        ]);

        Cache::put('idx.import.pos', [
            'status' => 'running',
            'items_total' => 0,
            'pages' => 0,
            'started_at' => now()->toISOString(),
            'window_days' => $this->days,
        ], now()->addHours(2));

        try {
            // Import IDX first (higher priority)
            logger()->info('import_all_pos.starting_idx');
            (new ImportPosLast30Days(
                pageSize: $this->pageSize,
                maxPages: $this->maxPages,
                days: $this->days,
                feed: 'idx',
            ))->handle($idx);

            // Then import VOW
            logger()->info('import_all_pos.starting_vow');
            (new ImportPosLast30Days(
                pageSize: $this->pageSize,
                maxPages: $this->maxPages,
                days: $this->days,
                feed: 'vow',
            ))->handle($idx);

            logger()->info('import_all_pos.completed');

            Cache::put('idx.import.pos', [
                'status' => 'completed',
                'finished_at' => now()->toISOString(),
                'window_days' => $this->days,
            ], now()->addHours(2));

        } catch (\Throwable $e) {
            logger()->error('import_all_pos.failed', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]);

            Cache::put('idx.import.pos', [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'finished_at' => now()->toISOString(),
            ], now()->addHours(2));

            throw $e;
        }
    }
}
