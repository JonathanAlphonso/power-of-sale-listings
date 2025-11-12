<?php

namespace App\Jobs;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BackfillListingMedia implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public bool $onlyMissing = true,
        public int $limit = 1000,
        public string $mediaQueue = 'media',
        public int $chunk = 500,
    ) {}

    /**
     * Ensure only one backfill job (any variant) is queued or running.
     */
    public function uniqueId(): string
    {
        return 'listing-media-backfill';
    }

    /**
     * Prevent overlapping media backfills which could enqueue duplicates.
     *
     * @return array<int, mixed>
     */
    public function middleware(): array
    {
        return [
            (new \Illuminate\Queue\Middleware\WithoutOverlapping('listing-media-backfill'))->expireAfter($this->timeout),
        ];
    }

    public function handle(): void
    {
        $query = Listing::query()->select(['id', 'external_id']);

        if ($this->onlyMissing) {
            $query->whereDoesntHave('media');
        }

        $enqueued = 0;

        $query->orderBy('id')->chunkById(max(50, $this->chunk), function ($listings) use (&$enqueued): void {
            foreach ($listings as $listing) {
                if ($enqueued >= $this->limit) {
                    break;
                }

                $key = (string) $listing->external_id; // Stores ListingKey for PropTx imports
                if ($key === '') {
                    continue;
                }

                SyncIdxMediaForListing::dispatch((int) $listing->id, $key)->onQueue($this->mediaQueue);
                $enqueued++;
            }
        });
    }
}
