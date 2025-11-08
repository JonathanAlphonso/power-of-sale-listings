<?php

declare(strict_types=1);

use App\Models\Listing;
use Illuminate\Support\Facades\Http;

it('queues and syncs media for listings', function (): void {
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');

    $listing = Listing::factory()->create([
        'external_id' => 'K1',
    ]);

    Http::fake([
        // Property import may be called by other code paths; ensure default OK.
        'idx.example/odata/Property*' => Http::response(['value' => []], 200),
        'idx.example/odata/Media*' => Http::response([
            'value' => [
                [
                    'MediaURL' => 'https://example.com/img/1.jpg',
                    'MediaType' => 'image/jpeg',
                    'ResourceName' => 'Property',
                    'ResourceRecordKey' => 'K1',
                    'MediaModificationTimestamp' => now()->toISOString(),
                    'ImageSizeDescription' => 'Large',
                    'LongDescription' => 'Front',
                    'MediaKey' => 'M1',
                    'MediaCategory' => 'Photo',
                    'MediaStatus' => 'Active',
                ],
            ],
        ], 200),
    ]);

    $this->artisan('listing-media:backfill --all --limit=1')
        ->assertExitCode(0);

    $listing->refresh();
    expect($listing->media()->count())->toBeGreaterThan(0);
});
