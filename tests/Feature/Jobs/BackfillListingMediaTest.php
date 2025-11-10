<?php

declare(strict_types=1);

use App\Jobs\BackfillListingMedia;
use App\Jobs\SyncIdxMediaForListing;
use App\Models\Listing;
use Illuminate\Support\Facades\Bus;

it('dispatches media sync jobs for listings missing media', function (): void {
    // Create two listings without media
    $a = Listing::factory()->create(['external_id' => 'K-ONE']);
    $b = Listing::factory()->create(['external_id' => 'K-TWO']);

    Bus::fake();

    // Run backfill with a tight limit to keep test bounded
    (new BackfillListingMedia(onlyMissing: true, limit: 2, mediaQueue: 'media', chunk: 100))->handle();

    Bus::assertDispatched(SyncIdxMediaForListing::class, function ($job) use ($a, $b): bool {
        // Collect dispatched listing IDs
        static $ids = [];
        $ids[] = $job->listingId;

        return in_array($job->listingId, [$a->id, $b->id], true);
    });
});
