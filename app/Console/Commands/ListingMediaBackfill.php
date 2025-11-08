<?php

namespace App\Console\Commands;

use App\Jobs\SyncIdxMediaForListing;
use App\Models\Listing;
use Illuminate\Console\Command;

class ListingMediaBackfill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'listing-media:backfill
        {--all : Process all listings}
        {--only-missing : Only listings with no media (default)}
        {--limit=1000 : Max listings to enqueue}
        {--queue=media : Queue name for jobs}
        {--chunk=500 : Chunk size for iterating listings}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill listing media by fetching image URLs from the Media resource and syncing records';

    public function handle(): int
    {
        $processAll = (bool) $this->option('all');
        $onlyMissing = (bool) $this->option('only-missing');
        $limit = (int) $this->option('limit');
        $queue = (string) $this->option('queue');
        $chunk = max(50, (int) $this->option('chunk'));

        if ($processAll) {
            $onlyMissing = false;
        }

        $query = Listing::query()->select(['id', 'external_id']);
        if ($onlyMissing && ! $processAll) {
            $query->whereDoesntHave('media');
        }

        $count = 0;
        $this->info('Enqueuing media sync jobs...');

        $query->orderBy('id')->chunkById($chunk, function ($listings) use (&$count, $limit, $queue) {
            foreach ($listings as $listing) {
                if ($count >= $limit) {
                    break;
                }

                $key = (string) $listing->external_id; // external_id stores ListingKey for PropTx imports
                if ($key === '') {
                    continue;
                }

                SyncIdxMediaForListing::dispatch((int) $listing->id, $key)->onQueue($queue);
                $count++;
            }
        });

        $this->info("Queued {$count} listing media sync jobs.");

        return self::SUCCESS;
    }
}
