<?php

declare(strict_types=1);

use App\Services\Idx\IdxClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('returns only live listings using StandardStatus', function (): void {
    // Ensure IDX client is considered enabled
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');

    // Avoid cache interference between runs
    Cache::flush();

    // Fake Property and Media endpoints
    Http::fake([
        // Return a mix of statuses, only one truly Active
        'idx.example/odata/Property*' => Http::response([
            'value' => [
                [
                    'ListingKey' => '1',
                    'StandardStatus' => 'Active',
                    'ListPrice' => 123456,
                    'ModificationTimestamp' => now()->toISOString(),
                ],
                [
                    'ListingKey' => '2',
                    'StandardStatus' => 'Closed',
                    'ListPrice' => 654321,
                    'ModificationTimestamp' => now()->subDay()->toISOString(),
                ],
                [
                    'ListingKey' => '3',
                    // Missing StandardStatus, but ContractStatus says Active (should be ignored)
                    'ContractStatus' => 'Active',
                    'ListPrice' => 111111,
                    'ModificationTimestamp' => now()->subDays(2)->toISOString(),
                ],
            ],
        ], 200),
        // No images needed for this test
        'idx.example/odata/Media*' => Http::response(['value' => []], 200),
        '*' => Http::response(['value' => []], 200),
    ]);

    /** @var IdxClient $client */
    $client = app(IdxClient::class);

    $listings = $client->fetchListings(4);

    expect($listings)->toBeArray();
    expect(count($listings))->toBe(1);
    expect(strtolower((string) ($listings[0]['status'] ?? '')))->toBe('active');

    // Ensure only StandardStatus=Active listings are returned (local filtering safeguard)
    expect(collect($listings)->every(fn ($l) => strtolower((string) ($l['status'] ?? '')) === 'active'))
        ->toBeTrue();
});
