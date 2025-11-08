<?php

namespace App\Console\Commands;

use App\Models\ListingMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ListingMediaPrune extends Command
{
    protected $signature = 'listing-media:prune
        {--disk= : Storage disk (default: config media.disk)}
        {--prefix= : Path prefix (default: config media.path_prefix)}
        {--force : Delete detected orphan files}
        {--include-stored=false : Also delete files still referenced in DB (dangerous)}
    ';

    protected $description = 'List or delete orphaned media files under the configured storage path';

    public function handle(): int
    {
        $disk = (string) ($this->option('disk') ?: config('media.disk', 'public'));
        $prefix = trim((string) ($this->option('prefix') ?: config('media.path_prefix', 'listings')), '/');
        $force = (bool) $this->option('force');
        $includeStored = filter_var((string) $this->option('include-stored'), FILTER_VALIDATE_BOOLEAN);

        $this->info("Scanning disk='{$disk}' prefix='{$prefix}'...");
        $allFiles = collect(Storage::disk($disk)->allFiles($prefix));
        $dbPaths = ListingMedia::query()->whereNotNull('stored_path')->pluck('stored_path');

        $orphans = $allFiles->diff($dbPaths);
        $this->line('Total files: '.$allFiles->count());
        $this->line('DB-referenced files: '.$dbPaths->count());
        $this->line('Orphans: '.$orphans->count());

        if (! $force) {
            foreach ($orphans->take(20) as $path) {
                $this->line(" - {$path}");
            }
            if ($orphans->count() > 20) {
                $this->line(' ...');
            }
            $this->warn('Run with --force to delete orphans.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($orphans as $path) {
            if (Storage::disk($disk)->delete($path)) {
                $deleted++;
            }
        }

        if ($includeStored) {
            // Danger: remove files that are still referenced - use with caution
            foreach ($dbPaths as $path) {
                Storage::disk($disk)->delete((string) $path);
            }
        }

        $this->info("Deleted {$deleted} orphan files.");

        return self::SUCCESS;
    }
}
