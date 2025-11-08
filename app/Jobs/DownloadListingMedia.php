<?php

namespace App\Jobs;

use App\Models\ListingMedia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DownloadListingMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(public int $listingMediaId)
    {
        $this->onQueue('media');
    }

    public function handle(): void
    {
        try {
            /** @var ListingMedia|null $media */
            $media = ListingMedia::query()->find($this->listingMediaId);

            if ($media === null) {
                return;
            }

            $url = (string) ($media->preview_url ?: $media->url);
            if ($url === '') {
                return;
            }

            $disk = (string) config('media.disk', 'public');
            $prefix = trim((string) config('media.path_prefix', 'listings'), '/');

            $response = Http::timeout(15)->retry(2, 250)->get($url);
            if ($response->failed()) {
                self::bumpMetrics('download', false, 'HTTP '.$response->status());

                return;
            }

            $contentType = (string) $response->header('Content-Type', 'image/jpeg');
            $extension = self::extensionFromMime($contentType) ?? 'jpg';

            $path = sprintf('%s/%d/%d.%s', $prefix, (int) $media->listing_id, (int) $media->id, $extension);

            Storage::disk($disk)->put($path, (string) $response->body(), 'public');

            $media->forceFill([
                'stored_disk' => $disk,
                'stored_path' => $path,
                'stored_at' => now(),
            ])->save();

            self::bumpMetrics('download', true);
        } catch (\Throwable $e) {
            self::bumpMetrics('download', false, $e->getMessage());
        }
    }

    private static function extensionFromMime(string $mime): ?string
    {
        return match (strtolower(trim($mime))) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
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
