<?php

namespace App\Console\Commands;

use App\Models\Listing;
use Illuminate\Console\Command;

class GenerateListingSlugs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'listings:generate-slugs {--force : Regenerate slugs for all listings, even those that already have one}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate SEO-friendly slugs for listings';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Listing::query();

        if (! $this->option('force')) {
            $query->whereNull('slug');
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No listings need slug generation.');

            return self::SUCCESS;
        }

        $this->info("Generating slugs for {$count} listings...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->chunkById(500, function ($listings) use ($bar) {
            foreach ($listings as $listing) {
                $listing->slug = $listing->generateSlug();
                $listing->saveQuietly();
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done!');

        return self::SUCCESS;
    }
}
