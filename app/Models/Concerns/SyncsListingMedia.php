<?php

namespace App\Models\Concerns;

use Illuminate\Support\Arr;

trait SyncsListingMedia
{
    /**
     * @param  array<int|string, mixed>  $imageSets
     * @param  array<int, string>  $fallbackImages
     */
    protected function syncMediaFromPayload(array $imageSets, array $fallbackImages): void
    {
        $this->media()->delete();

        if ($imageSets === []) {
            foreach ($fallbackImages as $index => $imageUrl) {
                $url = (string) $imageUrl;

                if ($url === '') {
                    continue;
                }

                $media = $this->media()->create([
                    'media_type' => 'image',
                    'label' => null,
                    'position' => $index,
                    'is_primary' => $index === 0,
                    'url' => $url,
                    'preview_url' => $url,
                    'variants' => [],
                    'meta' => [
                        'source' => 'payload.images',
                    ],
                ]);

                if (config('media.auto_download')) {
                    \App\Jobs\DownloadListingMedia::dispatch((int) $media->id);
                }
            }

            return;
        }

        foreach ($imageSets as $index => $imageSet) {
            $variants = Arr::wrap($imageSet['sizes'] ?? []);

            $primaryUrl = $imageSet['url'] ?? Arr::first($variants, static function ($value): bool {
                return is_string($value) && $value !== '';
            });

            if (! is_string($primaryUrl) || $primaryUrl === '') {
                $fallbackUrl = $fallbackImages[$index] ?? Arr::first($fallbackImages);

                if (! is_string($fallbackUrl) || $fallbackUrl === '') {
                    continue;
                }

                $primaryUrl = $fallbackUrl;
            }

            $media = $this->media()->create([
                'media_type' => 'image',
                'label' => $imageSet['description'] ?? null,
                'position' => $index,
                'is_primary' => $index === 0,
                'url' => $primaryUrl,
                'preview_url' => $variants['900'] ?? $variants['600'] ?? $primaryUrl,
                'variants' => $variants,
                'meta' => array_filter([
                    'source' => 'payload.imageSets',
                    'description' => $imageSet['description'] ?? null,
                ]),
            ]);

            if (config('media.auto_download')) {
                \App\Jobs\DownloadListingMedia::dispatch((int) $media->id);
            }
        }
    }
}
