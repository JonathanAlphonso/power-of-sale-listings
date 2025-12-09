<?php

declare(strict_types=1);

use App\Jobs\ImportVowPowerOfSale;
use App\Models\Listing;
use App\Services\Idx\ListingUpserter;
use Illuminate\Support\Facades\Http;

it('normalizes long board names to short codes in VOW import', function (): void {
    config()->set('services.vow.base_uri', 'https://vow.example/odata/');
    config()->set('services.vow.token', 'vow-token');

    Http::fake([
        'vow.example/odata/Property*' => Http::response([
            'value' => [[
                'ListingKey' => 'X7289548',
                'ListingId' => 'X7289548',
                'OriginatingSystemName' => 'Toronto Regional Real Estate Board',
                'StandardStatus' => 'Closed',
                'MlsStatus' => 'Sold',
                'ContractStatus' => 'Unavailable',
                'UnparsedAddress' => "912 O'reilly Crescent, Shelburne, ON L9V 2S7",
                'StreetNumber' => '912',
                'StreetName' => "O'reilly",
                'City' => 'Shelburne',
                'StateOrProvince' => 'ON',
                'PostalCode' => 'L9V 2S7',
                'ListPrice' => 884900,
                'OriginalListPrice' => 884900,
                'ModificationTimestamp' => now()->toISOString(),
                'PropertyType' => 'Residential Freehold',
                'PropertySubType' => 'Detached',
                'BathroomsTotalInteger' => 4,
                'BedroomsTotal' => 5,
                'PublicRemarks' => 'Power of Sale',
                'TransactionType' => 'For Sale',
            ]],
        ], 200),
    ]);

    expect(Listing::query()->count())->toBe(0);

    (new ImportVowPowerOfSale(pageSize: 10, maxPages: 1))->handle(app(ListingUpserter::class));

    $listing = Listing::query()->where('external_id', 'X7289548')->first();
    expect($listing)->not->toBeNull();
    expect($listing->board_code)->toBe('TRREB');
});
