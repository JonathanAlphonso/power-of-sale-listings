<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\ListingMedia;
use Illuminate\Support\Facades\Storage;

it('reports no listings when none are soft-deleted beyond retention', function (): void {
    Storage::fake('public');

    // Create a listing that was deleted recently (within retention)
    $listing = Listing::factory()->create();
    $listing->delete();

    $this->artisan('listings:cleanup-deleted --days=30')
        ->expectsOutputToContain('No soft-deleted listings found beyond retention period')
        ->assertExitCode(0);
});

it('identifies soft-deleted listings and media for cleanup in dry-run', function (): void {
    Storage::fake('public');

    // Create a listing soft-deleted 40 days ago (beyond 30-day retention)
    $listing = Listing::factory()->create();
    $media = ListingMedia::factory()->for($listing)->create([
        'stored_disk' => 'public',
        'stored_path' => 'listings/'.$listing->id.'/1.jpg',
        'stored_at' => now(),
    ]);
    Storage::disk('public')->put($media->stored_path, 'image-data');

    // Soft-delete the listing 40 days ago
    $listing->deleted_at = now()->subDays(40);
    $listing->save();

    // Dry-run should report but not delete
    $this->artisan('listings:cleanup-deleted --days=30')
        ->expectsOutputToContain('Found 1 soft-deleted listing(s)')
        ->expectsOutputToContain('Dry-run mode')
        ->assertExitCode(0);

    // Media should still exist
    expect(ListingMedia::find($media->id))->not->toBeNull();
    Storage::disk('public')->assertExists($media->stored_path);
});

it('deletes media files and records with --force', function (): void {
    Storage::fake('public');

    $listing = Listing::factory()->create();
    $media = ListingMedia::factory()->for($listing)->create([
        'stored_disk' => 'public',
        'stored_path' => 'listings/'.$listing->id.'/1.jpg',
        'stored_at' => now(),
    ]);
    Storage::disk('public')->put($media->stored_path, 'image-data');

    $listing->deleted_at = now()->subDays(40);
    $listing->save();

    $this->artisan('listings:cleanup-deleted --days=30 --force')
        ->assertExitCode(0);

    // Media record should be deleted
    expect(ListingMedia::find($media->id))->toBeNull();
    // Media file should be deleted
    Storage::disk('public')->assertMissing($media->stored_path);
    // Listing should still exist (soft-deleted)
    expect(Listing::withTrashed()->find($listing->id))->not->toBeNull();
});

it('hard-deletes listings with --hard-delete --force', function (): void {
    Storage::fake('public');

    $listing = Listing::factory()->create();
    $media = ListingMedia::factory()->for($listing)->create([
        'stored_disk' => 'public',
        'stored_path' => 'listings/'.$listing->id.'/1.jpg',
        'stored_at' => now(),
    ]);
    Storage::disk('public')->put($media->stored_path, 'image-data');

    $listing->deleted_at = now()->subDays(40);
    $listing->save();

    $this->artisan('listings:cleanup-deleted --days=30 --hard-delete --force')
        ->assertExitCode(0);

    // Media should be deleted
    expect(ListingMedia::find($media->id))->toBeNull();
    Storage::disk('public')->assertMissing($media->stored_path);
    // Listing should be completely gone
    expect(Listing::withTrashed()->find($listing->id))->toBeNull();
});

it('respects custom retention days', function (): void {
    Storage::fake('public');

    // Listing deleted 10 days ago
    $listing = Listing::factory()->create();
    $listing->deleted_at = now()->subDays(10);
    $listing->save();

    // With 30-day retention, should not be cleaned
    $this->artisan('listings:cleanup-deleted --days=30')
        ->expectsOutputToContain('No soft-deleted listings found beyond retention period')
        ->assertExitCode(0);

    // With 7-day retention, should be identified
    $this->artisan('listings:cleanup-deleted --days=7')
        ->expectsOutputToContain('Found 1 soft-deleted listing(s)')
        ->assertExitCode(0);
});
