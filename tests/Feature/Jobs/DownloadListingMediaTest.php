<?php

declare(strict_types=1);

use App\Jobs\DownloadListingMedia;
use App\Models\Listing;
use App\Models\ListingMedia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('downloads and stores listing media', function (): void {
    Storage::fake('public');
    Http::fake([
        'example.com/*' => Http::response('fake-image', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $listing = Listing::factory()->create();
    $media = ListingMedia::factory()->for($listing)->create([
        'url' => 'https://example.com/image.jpg',
        'preview_url' => 'https://example.com/image.jpg',
    ]);

    (new DownloadListingMedia($media->id))->handle();

    $media->refresh();

    expect($media->stored_disk)->toBe('public')
        ->and($media->stored_path)->not->toBeNull()
        ->and($media->stored_at)->not->toBeNull();

    Storage::disk('public')->assertExists($media->stored_path);
});
