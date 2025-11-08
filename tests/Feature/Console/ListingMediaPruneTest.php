<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\ListingMedia;
use Illuminate\Support\Facades\Storage;

it('lists and deletes orphan media files', function (): void {
    Storage::fake('public');

    // Create one DB-referenced file and one orphan file
    $listing = Listing::factory()->create();
    $media = ListingMedia::factory()->for($listing)->create([
        'stored_disk' => 'public',
        'stored_path' => 'listings/'.$listing->id.'/1.jpg',
        'stored_at' => now(),
    ]);

    Storage::disk('public')->put($media->stored_path, 'img');
    Storage::disk('public')->put('listings/'.$listing->id.'/orphan.jpg', 'orphan');

    // Dry-run
    $this->artisan('listing-media:prune')
        ->expectsOutputToContain('Orphans: 1')
        ->assertExitCode(0);

    // Force delete orphans
    $this->artisan('listing-media:prune --force')
        ->expectsOutputToContain('Deleted 1 orphan files')
        ->assertExitCode(0);

    Storage::disk('public')->assertMissing('listings/'.$listing->id.'/orphan.jpg');
    Storage::disk('public')->assertExists($media->stored_path);
});
