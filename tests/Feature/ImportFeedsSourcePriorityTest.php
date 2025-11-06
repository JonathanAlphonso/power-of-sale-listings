<?php

declare(strict_types=1);

use App\Jobs\ImportIdxPowerOfSale;
use App\Jobs\ImportVowPowerOfSale;
use App\Models\Listing;
use App\Services\Idx\IdxClient;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    // Configure both feeds
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'idx-token');
    config()->set('services.vow.base_uri', 'https://vow.example/odata/');
    config()->set('services.vow.token', 'vow-token');

    Http::fake([
        // IDX feed
        'idx.example/odata/Property*' => Http::response([
            'value' => [[
                'ListingKey' => 'X1',
                'ListingId' => 'M1',
                'OriginatingSystemName' => 'TRREB',
                'StandardStatus' => 'Active',
                'TransactionType' => 'For Sale',
                'UnparsedAddress' => '1 Main St, Toronto, ON',
                'City' => 'Toronto',
                'StateOrProvince' => 'ON',
                'PostalCode' => 'M1M1M1',
                'ModificationTimestamp' => now()->toISOString(),
                'ListPrice' => 1000000,
                'PublicRemarks' => 'Power of Sale',
            ]],
        ], 200),
        // VOW feed
        'vow.example/odata/Property*' => Http::response([
            'value' => [[
                'ListingKey' => 'X1', // same listing
                'ListingId' => 'M1',
                'OriginatingSystemName' => 'TRREB',
                'StandardStatus' => 'Active',
                'TransactionType' => 'For Sale',
                'UnparsedAddress' => '1 Main St, Toronto, ON',
                'City' => 'Toronto',
                'StateOrProvince' => 'ON',
                'PostalCode' => 'M1M1M1',
                'ModificationTimestamp' => now()->toISOString(),
                'ListPrice' => 1000000,
                'PublicRemarks' => 'Power of Sale',
            ]],
        ], 200),
        '*' => Http::response(['value' => []], 200),
    ]);
});

it('upgrades VOW to IDX when both present', function (): void {
    $idx = app(IdxClient::class);

    // Import VOW first
    (new ImportVowPowerOfSale(pageSize: 1, maxPages: 1))->handle($idx);

    $listing = Listing::first();
    expect($listing)->not->toBeNull();
    expect($listing->source)->not->toBeNull();
    expect($listing->source->slug)->toBe('vow');

    // Then import IDX
    (new ImportIdxPowerOfSale(pageSize: 1, maxPages: 1))->handle($idx);

    $listing->refresh();
    expect($listing->source->slug)->toBe('idx');
    expect(Listing::count())->toBe(1);
});

it('keeps IDX when VOW ingests after', function (): void {
    $idx = app(IdxClient::class);

    // Import IDX first
    (new ImportIdxPowerOfSale(pageSize: 1, maxPages: 1))->handle($idx);

    $listing = Listing::first();
    expect($listing)->not->toBeNull();
    expect($listing->source)->not->toBeNull();
    expect($listing->source->slug)->toBe('idx');

    // Then import VOW
    (new ImportVowPowerOfSale(pageSize: 1, maxPages: 1))->handle($idx);

    $listing->refresh();
    expect($listing->source->slug)->toBe('idx');
    expect(Listing::count())->toBe(1);
});
