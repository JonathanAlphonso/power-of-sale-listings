<?php

declare(strict_types=1);

use App\Services\Idx\IdxClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('caches listing results to avoid repeated network calls', function (): void {
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');

    Cache::flush();

    Http::fake([
        'idx.example/odata/Property*' => Http::response([
            'value' => [
                [
                    'ListingKey' => 'C1',
                    'StandardStatus' => 'Active',
                    'ModificationTimestamp' => now()->toISOString(),
                ],
            ],
        ], 200),
        'idx.example/odata/Media*' => Http::response(['value' => []], 200),
        '*' => Http::response(['value' => []], 200),
    ]);

    /** @var IdxClient $client */
    $client = app(IdxClient::class);

    // First call should hit the network
    $first = $client->fetchListings(1);
    expect($first)->toBeArray()->not->toBeEmpty();

    // Second call should return cached response without additional HTTP requests
    $second = $client->fetchListings(1);
    expect($second)->toBeArray()->toEqual($first);

    // Only the Property + Media requests from the first call should have been sent
    Http::assertSentCount(2);
});
