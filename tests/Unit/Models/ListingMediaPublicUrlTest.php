<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\ListingMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->group('unit');

it('returns storage url when stored_path is present', function (): void {
    Storage::fake('public');

    $listing = Listing::factory()->create();
    $media = ListingMedia::factory()->for($listing)->create([
        'url' => 'https://example.com/image.jpg',
        'preview_url' => 'https://example.com/preview.jpg',
        'stored_disk' => 'public',
        'stored_path' => 'listings/'.$listing->id.'/1.jpg',
    ]);

    Storage::disk('public')->put($media->stored_path, 'fake');

    $url = $media->public_url;
    expect($url)->toContain('/storage/');
});

it('falls back to preview then url when not stored', function (): void {
    $listing = Listing::factory()->create();
    $media = ListingMedia::factory()->for($listing)->create([
        'url' => 'https://example.com/image.jpg',
        'preview_url' => 'https://example.com/preview.jpg',
        'stored_disk' => null,
        'stored_path' => null,
    ]);

    expect($media->public_url)->toBe('https://example.com/preview.jpg');
});
