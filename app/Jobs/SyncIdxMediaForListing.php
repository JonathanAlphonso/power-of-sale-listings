<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Services\Idx\IdxClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

class SyncIdxMediaForListing implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 5;

    public int $maxExceptions = 3;

    public function __construct(public int $listingId, public string $listingKey) {}

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('media-api')];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(2);
    }

    public function handle(IdxClient $idx): void
    {
        try {
            /** @var Listing|null $listing */
            $listing = Listing::query()->find($this->listingId);
            if ($listing === null) {
                return;
            }

            $mediaItems = $idx->fetchPropertyMedia($this->listingKey, 25);

            // Replace existing media with latest API-set
            $listing->media()->delete();

            $position = 0;
            foreach ($mediaItems as $item) {
                $url = (string) ($item['url'] ?? '');
                if ($url === '') {
                    continue;
                }

                $media = $listing->media()->create([
                    'media_type' => 'image',
                    'label' => $item['label'] ?? null,
                    'position' => $position,
                    'is_primary' => $position === 0,
                    'url' => $url,
                    'preview_url' => $url,
                    'variants' => [],
                    'meta' => array_filter([
                        'media_key' => $item['media_key'] ?? null,
                        'image_size' => $item['size'] ?? null,
                        'type' => $item['type'] ?? null,
                        'modified_at' => $item['modified_at'] ?? null,
                    ]),
                ]);

                $position++;

                if (config('media.auto_download')) {
                    DownloadListingMedia::dispatch((int) $media->id)->onQueue('media');
                }
            }

            self::bumpMetrics('sync', true);
        } catch (\Throwable $e) {
            self::bumpMetrics('sync', false, $e->getMessage());
        }
    }

    private static function bumpMetrics(string $scope, bool $success, ?string $error = null): void
    {
        try {
            $ttl = now()->addDay();
            $prefix = "media.metrics.{$scope}.";

            \Illuminate\Support\Facades\Cache::put($prefix.'last_at', now()->toIso8601String(), $ttl);
            \Illuminate\Support\Facades\Cache::put($prefix.'total', (int) \Illuminate\Support\Facades\Cache::get($prefix.'total', 0) + 1, $ttl);

            if ($success) {
                \Illuminate\Support\Facades\Cache::put($prefix.'success', (int) \Illuminate\Support\Facades\Cache::get($prefix.'success', 0) + 1, $ttl);
            } else {
                \Illuminate\Support\Facades\Cache::put($prefix.'other', (int) \Illuminate\Support\Facades\Cache::get($prefix.'other', 0) + 1, $ttl);
                if ($error) {
                    \Illuminate\Support\Facades\Cache::put($prefix.'last_error', \Illuminate\Support\Str::limit($error, 180), $ttl);
                }
            }
        } catch (\Throwable) {
            // best-effort
        }
    }
}
