<?php

namespace App\Jobs;

use App\Models\ReplicationCursor;
use App\Services\Idx\IdxClient;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportAllPowerOfSaleFeeds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(public int $pageSize = 500, public int $maxPages = 200) {}

    /**
     * Prevent overlapping full imports to avoid duplicate work.
     *
     * @return array<int, mixed>
     */
    public function middleware(): array
    {
        return [
            // Keep overlap protection with a generous expiry to avoid duplicates,
            // but allow re-queueing via stale detection in the UI if needed.
            (new \Illuminate\Queue\Middleware\WithoutOverlapping('pos-import-all'))->expireAfter($this->timeout),
        ];
    }

    public function handle(IdxClient $idx): void
    {
        logger()->info('import_all_pos.started', [
            'page_size' => $this->pageSize,
            'max_pages' => $this->maxPages,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Manual "Import Both" should start fresh and not be impacted by previous runs.
        // Reset replication cursors so the importer starts from the beginning.
        try {
            ReplicationCursor::query()
                ->whereIn('channel', ['idx.property.pos', 'vow.property.pos'])
                ->update([
                    'last_timestamp' => CarbonImmutable::create(1970, 1, 1, 0, 0, 0, 'UTC'),
                    'last_key' => '0',
                ]);
            logger()->info('import_all_pos.cursors_reset');
        } catch (\Throwable $e) {
            logger()->warning('import_all_pos.cursor_reset_failed', ['error' => $e->getMessage()]);
            // Best-effort; proceed even if cursor table missing
        }

        try {
            // Import IDX first (higher priority), then VOW
            logger()->info('import_all_pos.starting_idx');
            (new ImportIdxPowerOfSale($this->pageSize, $this->maxPages))->handle($idx);

            logger()->info('import_all_pos.starting_vow');
            (new ImportVowPowerOfSale($this->pageSize, $this->maxPages))->handle($idx);

            logger()->info('import_all_pos.completed');
        } catch (\Throwable $e) {
            logger()->error('import_all_pos.failed', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]);
            throw $e;
        }
    }
}
