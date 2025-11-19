<?php

declare(strict_types=1);

use App\Jobs\ImportIdxPowerOfSale;
use App\Models\Listing;
use App\Services\Idx\IdxClient;
use Illuminate\Support\Facades\Http;

it('imports power of sale listings end-to-end', function (): void {
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');

    Http::fake([
        // First page returns two items, one without OriginatingSystemName
        'idx.example/odata/Property*' => function ($request) {
            $query = [];
            parse_str((string) parse_url((string) $request->url(), PHP_URL_QUERY), $query);
            $skip = (int) ($query['$skip'] ?? 0);

            if ($skip > 0) {
                return Http::response(['value' => []], 200);
            }

            return Http::response([
                'value' => [
                    [
                        'ListingKey' => 'X12300001',
                        'ListingId' => 'A1',
                        'OriginatingSystemName' => 'TRREB',
                        'City' => 'Toronto',
                        'StateOrProvince' => 'ON',
                        'UnparsedAddress' => '1 King St W, Toronto, ON M5H 1A1',
                        'StreetNumber' => '1',
                        'StreetName' => 'King',
                        'StreetSuffix' => 'St W',
                        'StandardStatus' => 'Active',
                        'ListPrice' => 500000,
                        'ModificationTimestamp' => now()->toISOString(),
                        'PropertyType' => 'Residential Freehold',
                        'PropertySubType' => 'Detached',
                        'PublicRemarks' => 'Power of Sale opportunity',
                        'TransactionType' => 'For Sale',
                    ],
                    [
                        'ListingKey' => 'X12300002',
                        'ListingId' => 'A2',
                        // Missing OriginatingSystemName to trigger fallback
                        'City' => 'Ottawa',
                        'StateOrProvince' => 'ON',
                        'UnparsedAddress' => '9 Bank St, Ottawa, ON K1P 5N4',
                        'StreetNumber' => '9',
                        'StreetName' => 'Bank',
                        'StreetSuffix' => 'St',
                        'StandardStatus' => 'Active',
                        'ListPrice' => 600000,
                        'ModificationTimestamp' => now()->toISOString(),
                        'PropertyType' => 'Residential Freehold',
                        'PropertySubType' => 'Detached',
                        'PublicRemarks' => 'Power-of-Sale listing',
                        'TransactionType' => 'For Sale',
                    ],
                ],
            ], 200);
        },
    ]);

    expect(Listing::query()->count())->toBe(0);

    // Run job synchronously
    $job = new ImportIdxPowerOfSale(pageSize: 50, maxPages: 2);
    $job->handle(app(IdxClient::class));

    $listings = Listing::query()->get();
    expect($listings->count())->toBe(2);

    $first = Listing::query()->where('external_id', 'X12300001')->first();
    expect($first)->not->toBeNull();
    expect($first->board_code)->toBe('TRREB');

    $second = Listing::query()->where('external_id', 'X12300002')->first();
    expect($second)->not->toBeNull();
    // Falls back to UNKNOWN when OriginatingSystemName is missing
    expect($second->board_code)->toBe('UNKNOWN');
});

it('updates existing listing matched by board_code + mls_number without duplicate', function (): void {
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');

    // Existing listing with same board_code + mls_number but different external handling
    Listing::factory()->create([
        'external_id' => 'legacy-1',
        'board_code' => 'TRREB',
        'mls_number' => 'A1',
        'display_status' => 'Available',
        'list_price' => 400000,
        'modified_at' => now()->subDay(),
    ]);

    Http::fake([
        'idx.example/odata/Property*' => Http::response([
            'value' => [[
                'ListingKey' => 'X999',
                'ListingId' => 'A1',
                'OriginatingSystemName' => 'TRREB',
                'City' => 'Toronto',
                'StateOrProvince' => 'ON',
                'UnparsedAddress' => '1 King St W, Toronto, ON M5H 1A1',
                'StreetNumber' => '1',
                'StreetName' => 'King',
                'StreetSuffix' => 'St W',
                'StandardStatus' => 'Active',
                'ListPrice' => 550000,
                'ModificationTimestamp' => now()->toISOString(),
                'PropertyType' => 'Residential Freehold',
                'PropertySubType' => 'Detached',
                'PublicRemarks' => 'Power of Sale opportunity',
                'TransactionType' => 'For Sale',
            ]],
        ], 200),
    ]);

    $job = new ImportIdxPowerOfSale(pageSize: 50, maxPages: 1);
    $job->handle(app(IdxClient::class));

    // Still one listing, updated in place
    $rows = Listing::query()->where('board_code', 'TRREB')->where('mls_number', 'A1')->get();
    expect($rows->count())->toBe(1);
    expect((int) $rows->first()->list_price)->toBe(550000);
});

it('follows @odata.nextLink pagination when provided', function (): void {
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');

    Http::fake([
        // Second page by relative nextLink (register first so it is matched before wildcard)
        'idx.example/odata/Property?$skip=1&$top=1' => Http::response([
            'value' => [[
                'ListingKey' => 'N2', 'ListingId' => 'N2', 'OriginatingSystemName' => 'TRREB',
                'City' => 'Toronto', 'StateOrProvince' => 'ON', 'UnparsedAddress' => 'B',
                'StandardStatus' => 'Active', 'ListPrice' => 2, 'ModificationTimestamp' => now()->toISOString(),
                'PropertyType' => 'Residential Freehold', 'PropertySubType' => 'Detached', 'PublicRemarks' => 'Power of Sale', 'TransactionType' => 'For Sale',
            ]],
        ], 200),
        // First page with nextLink
        'idx.example/odata/Property*' => Http::response([
            'value' => [[
                'ListingKey' => 'N1', 'ListingId' => 'N1', 'OriginatingSystemName' => 'TRREB',
                'City' => 'Toronto', 'StateOrProvince' => 'ON', 'UnparsedAddress' => 'A',
                'StandardStatus' => 'Active', 'ListPrice' => 1, 'ModificationTimestamp' => now()->toISOString(),
                'PropertyType' => 'Residential Freehold', 'PropertySubType' => 'Detached', 'PublicRemarks' => 'Power of Sale', 'TransactionType' => 'For Sale',
            ]],
            '@odata.nextLink' => 'Property?$skip=1&$top=1',
        ], 200),
    ]);

    $job = new \App\Jobs\ImportIdxPowerOfSale(pageSize: 1, maxPages: 5);
    $job->handle(app(\App\Services\Idx\IdxClient::class));

    expect(\App\Models\Listing::query()->whereIn('external_id', ['N1', 'N2'])->count())->toBe(2);
});

it('updates a soft-deleted duplicate instead of inserting a new row', function (): void {
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');

    $trashed = Listing::factory()->create([
        'external_id' => 'legacy-trashed',
        'board_code' => 'UNKNOWN',
        'mls_number' => 'X12398695',
        'display_status' => 'Available',
        'list_price' => 400000,
        'modified_at' => now()->subDays(2),
    ]);
    $trashed->delete();

    Http::fake([
        'idx.example/odata/Property*' => Http::response([
            'value' => [[
                'ListingKey' => 'X12398695',
                'ListingId' => 'X12398695',
                // board_code will fallback to UNKNOWN
                'City' => 'Hamilton',
                'StateOrProvince' => 'ON',
                'UnparsedAddress' => '192 Fruitland Road, Hamilton, ON L8E 5J7',
                'StreetNumber' => '192',
                'StreetName' => 'Fruitland',
                'StreetSuffix' => 'Road',
                'StandardStatus' => 'Active',
                'ListPrice' => 519000,
                'ModificationTimestamp' => now()->toISOString(),
                'PropertyType' => 'Residential Freehold',
                'PropertySubType' => 'Detached',
                'PublicRemarks' => 'Power of Sale',
                'TransactionType' => 'For Sale',
            ]],
        ], 200),
    ]);

    $job = new ImportIdxPowerOfSale(pageSize: 50, maxPages: 1);
    $job->handle(app(IdxClient::class));

    // No duplicate inserted; soft-deleted row was updated
    // Listing should be restored and updated
    $rowsVisible = Listing::query()->where('board_code', 'UNKNOWN')->where('mls_number', 'X12398695')->get();
    expect($rowsVisible->count())->toBe(1);
    expect((int) $rowsVisible->first()->list_price)->toBe(519000);
});

it('does not append status history when import payload is unchanged', function (): void {
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');

    $payload = [
        'value' => [[
            'ListingKey' => 'IDEMP1',
            'ListingId' => 'IDEMP1',
            'OriginatingSystemName' => 'TRREB',
            'City' => 'Toronto',
            'StateOrProvince' => 'ON',
            'UnparsedAddress' => '1 King St W, Toronto, ON',
            'StreetNumber' => '1',
            'StreetName' => 'King',
            'StreetSuffix' => 'St W',
            'StandardStatus' => 'Active',
            'ListPrice' => 750000,
            'ModificationTimestamp' => now()->toISOString(),
            'PropertyType' => 'Residential Freehold',
            'PropertySubType' => 'Detached',
            'PublicRemarks' => 'Power of Sale',
            'TransactionType' => 'For Sale',
        ]],
    ];

    Http::fake([
        'idx.example/odata/Property*' => Http::response($payload, 200),
    ]);

    // First import
    (new \App\Jobs\ImportIdxPowerOfSale(pageSize: 50, maxPages: 1))->handle(app(\App\Services\Idx\IdxClient::class));

    $listing = \App\Models\Listing::query()->where('external_id', 'IDEMP1')->firstOrFail();
    $count1 = \App\Models\ListingStatusHistory::query()->where('listing_id', $listing->id)->count();
    expect($count1)->toBe(1);

    // Second import with identical payload should not create a new history row
    (new \App\Jobs\ImportIdxPowerOfSale(pageSize: 50, maxPages: 1))->handle(app(\App\Services\Idx\IdxClient::class));
    $count2 = \App\Models\ListingStatusHistory::query()->where('listing_id', $listing->id)->count();
    expect($count2)->toBe($count1);
});

it('imports thousands of recent power of sale listings with full public remarks', function (): void {
    config()->set('queue.default', 'sync');
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');

    expect(Listing::query()->count())->toBe(0);

    $now = now()->toImmutable();
    $items = [];

    for ($i = 1; $i <= 5000; $i++) {
        $items[] = [
            'ListingKey' => 'KPOS'.$i,
            'ListingId' => 'KPOS-'.$i,
            'OriginatingSystemName' => 'TRREB',
            'City' => 'Toronto',
            'StateOrProvince' => 'ON',
            'UnparsedAddress' => ''.$i.' King St W, Toronto, ON',
            'StreetNumber' => (string) $i,
            'StreetName' => 'King',
            'StreetSuffix' => 'St W',
            'StandardStatus' => 'Active',
            'ListPrice' => 500000 + $i,
            'ModificationTimestamp' => $now->subDays($i % 30)->toIso8601String(),
            'PropertyType' => 'Residential Freehold',
            'PropertySubType' => 'Detached',
            'PublicRemarks' => 'Power of Sale '.str_repeat('Long remarks ', 10),
            'TransactionType' => 'For Sale',
        ];
    }

    Http::fake([
        'idx.example/odata/Media*' => Http::response(['value' => []], 200),
        'idx.example/odata/Property*' => function ($request) use (&$items) {
            $query = [];
            parse_str((string) parse_url((string) $request->url(), PHP_URL_QUERY), $query);
            $top = (int) ($query['$top'] ?? 100);

            static $offset = 0;

            if ($offset >= count($items)) {
                return Http::response(['value' => []], 200);
            }

            $slice = array_slice($items, $offset, $top);
            $offset += count($slice);

            return Http::response(['value' => $slice], 200);
        },
    ]);

    $job = new ImportIdxPowerOfSale(pageSize: 100, maxPages: 100);
    $job->handle(app(IdxClient::class));

    $count = Listing::query()
        ->where('external_id', 'like', 'KPOS%')
        ->whereNotNull('public_remarks')
        ->where('public_remarks', '!=', '')
        ->where('modified_at', '>=', $now->subDays(30))
        ->count();

    expect($count)->toBe(5000);
});
