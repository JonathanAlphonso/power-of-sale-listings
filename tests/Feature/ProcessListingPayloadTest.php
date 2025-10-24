<?php

declare(strict_types=1);

use App\Jobs\ProcessListingPayload;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\Source;

it('processes payloads via queued job and normalises data', function (): void {
    $source = Source::factory()->create([
        'slug' => 'treb',
        'external_identifier' => 'TREB',
    ]);

    $municipality = Municipality::factory()->create([
        'name' => 'Brampton',
        'slug' => 'on-brampton',
        'province' => 'ON',
    ]);

    $payload = [
        '_id' => 'TREB-W12449903',
        'gid' => 'TREB',
        'listingID' => 'W12449903',
        'displayStatus' => 'Available',
        'availability' => 'A',
        'class' => 'CONDO',
        'typeName' => 'Condo Townhouse',
        'style' => '2-Storey',
        'saleOrRent' => 'RENT',
        'currency' => 'CAD',
        'streetNumber' => '9',
        'streetName' => 'Lancewood',
        'streetAddress' => '9 Lancewood Cres',
        'city' => 'Brampton',
        'district' => 'Brampton',
        'postalCode' => 'L6S 5Y6',
        'imageSets' => [
            [
                'url' => 'https://example.test/full.jpg',
                'sizes' => [
                    '600' => 'https://example.test/600.jpg',
                ],
            ],
        ],
    ];

    $job = new ProcessListingPayload($payload, [
        'source' => $source,
        'municipality' => $municipality,
        'ingestion_batch_id' => 'job-test',
    ]);

    $job->handle();

    $listing = Listing::query()
        ->with(['source', 'municipality', 'media'])
        ->where('external_id', 'TREB-W12449903')
        ->first();

    expect($listing)->not->toBeNull();
    expect($listing?->source?->is($source))->toBeTrue();
    expect($listing?->municipality?->is($municipality))->toBeTrue();
    expect($listing?->ingestion_batch_id)->toBe('job-test');
    expect($listing?->media)->toHaveCount(1);
});
