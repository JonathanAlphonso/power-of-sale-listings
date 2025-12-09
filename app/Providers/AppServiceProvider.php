<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiters for queued jobs.
     */
    protected function configureRateLimiting(): void
    {
        // Rate limiter for IDX API media sync requests
        RateLimiter::for('media-api', function (object $job) {
            $limit = (int) config('media.rate_limit_api', 120);

            return Limit::perMinute($limit)->by('media-api');
        });

        // Rate limiter for media file downloads
        RateLimiter::for('media-download', function (object $job) {
            $limit = (int) config('media.rate_limit_download', 60);

            return Limit::perMinute($limit)->by('media-download');
        });
    }
}
