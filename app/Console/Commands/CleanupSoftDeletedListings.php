<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Models\ListingMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupSoftDeletedListings extends Command
{
    protected $signature = 'listings:cleanup-deleted
        {--days=30 : Days since soft-delete before cleanup (default: 30)}
        {--hard-delete : Also hard-delete the listing records (default: media only)}
        {--force : Actually perform cleanup (default: dry-run)}
        {--disk= : Storage disk for media files (default: config media.disk)}
    ';

    protected $description = 'Clean up media files and optionally hard-delete listings that have been soft-deleted beyond the retention period';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $hardDelete = (bool) $this->option('hard-delete');
        $force = (bool) $this->option('force');
        $disk = (string) ($this->option('disk') ?: config('media.disk', 'public'));

        $cutoff = now()->subDays($days);

        $this->info("Finding listings soft-deleted before {$cutoff->toDateTimeString()} ({$days} days ago)...");

        $query = Listing::onlyTrashed()
            ->where('deleted_at', '<', $cutoff);

        $totalListings = $query->count();

        if ($totalListings === 0) {
            $this->info('No soft-deleted listings found beyond retention period.');

            return self::SUCCESS;
        }

        $this->line("Found {$totalListings} soft-deleted listing(s) to process.");

        $mediaDeleted = 0;
        $filesDeleted = 0;
        $listingsDeleted = 0;

        $query->with('media')->chunkById(100, function ($listings) use (
            $force,
            $hardDelete,
            $disk,
            &$mediaDeleted,
            &$filesDeleted,
            &$listingsDeleted
        ) {
            foreach ($listings as $listing) {
                foreach ($listing->media as $media) {
                    if ($force && $media->stored_path) {
                        if (Storage::disk($disk)->exists($media->stored_path)) {
                            Storage::disk($disk)->delete($media->stored_path);
                            $filesDeleted++;
                        }
                    }
                    if ($force) {
                        $media->delete();
                    }
                    $mediaDeleted++;
                }

                if ($hardDelete) {
                    if ($force) {
                        $listing->forceDelete();
                    }
                    $listingsDeleted++;
                }
            }
        });

        $this->newLine();
        $this->table(
            ['Action', 'Count', 'Status'],
            [
                ['Media records', $mediaDeleted, $force ? 'Deleted' : 'Would delete'],
                ['Media files', $filesDeleted, $force ? 'Deleted' : 'Would delete'],
                ['Listings (hard)', $listingsDeleted, $hardDelete ? ($force ? 'Deleted' : 'Would delete') : 'Skipped'],
            ]
        );

        if (! $force) {
            $this->warn('Dry-run mode. Run with --force to actually delete.');
        }

        return self::SUCCESS;
    }
}
