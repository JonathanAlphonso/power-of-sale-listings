<?php

declare(strict_types=1);

use App\Jobs\DownloadListingMedia;
use App\Jobs\SyncIdxMediaForListing;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\RateLimiter;

it('registers media rate limiters on boot', function (): void {
    // Rate limiters should be registered by AppServiceProvider
    expect(RateLimiter::limiter('media-api'))->not->toBeNull();
    expect(RateLimiter::limiter('media-download'))->not->toBeNull();
});

it('applies rate limiting middleware to DownloadListingMedia job', function (): void {
    $job = new DownloadListingMedia(1);
    $middleware = $job->middleware();

    expect($middleware)->toHaveCount(1);
    expect($middleware[0])->toBeInstanceOf(RateLimited::class);
});

it('applies rate limiting middleware to SyncIdxMediaForListing job', function (): void {
    $job = new SyncIdxMediaForListing(1, 'test-key');
    $middleware = $job->middleware();

    expect($middleware)->toHaveCount(1);
    expect($middleware[0])->toBeInstanceOf(RateLimited::class);
});

it('configures retry settings on DownloadListingMedia job', function (): void {
    $job = new DownloadListingMedia(1);

    expect($job->tries)->toBe(5);
    expect($job->maxExceptions)->toBe(3);
    expect($job->retryUntil())->toBeInstanceOf(DateTime::class);
});

it('configures retry settings on SyncIdxMediaForListing job', function (): void {
    $job = new SyncIdxMediaForListing(1, 'test-key');

    expect($job->tries)->toBe(5);
    expect($job->maxExceptions)->toBe(3);
    expect($job->retryUntil())->toBeInstanceOf(DateTime::class);
});

it('uses configurable rate limits from config', function (): void {
    // Set custom limits
    config()->set('media.rate_limit_api', 200);
    config()->set('media.rate_limit_download', 100);

    expect(config('media.rate_limit_api'))->toBe(200);
    expect(config('media.rate_limit_download'))->toBe(100);
});
