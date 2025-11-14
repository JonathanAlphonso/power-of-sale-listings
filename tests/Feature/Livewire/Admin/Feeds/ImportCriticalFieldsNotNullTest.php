<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

test('critical listing fields are never null after import', function (): void {
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');
    config()->set('services.vow.base_uri', 'https://idx.example/odata/');
    config()->set('services.vow.token', 'test-token');
    config()->set('queue.default', 'sync');

    Http::fake([
        'idx.example/odata/Property*' => function ($request) {
            $query = [];
            parse_str((string) parse_url((string) $request->url(), PHP_URL_QUERY), $query);
            $skip = (int) ($query['$skip'] ?? 0);
            if ($skip > 0) {
                return Http::response(['value' => []], 200);
            }

            return Http::response([
                'value' => [[
                    'ListingKey' => 'KCRIT1',
                    'ListingId' => 'CRIT-100',
                    'OriginatingSystemName' => 'TRREB',
                    'City' => 'Toronto',
                    'StateOrProvince' => 'ON',
                    'UnparsedAddress' => '1 King St W, Toronto, ON',
                    'StreetNumber' => '1',
                    'StreetName' => 'King',
                    'StreetSuffix' => 'St W',
                    'StandardStatus' => 'Active',
                    'ListPrice' => 1000000,
                    'ModificationTimestamp' => now()->toISOString(),
                    'PropertyType' => 'Residential Freehold',
                    'PropertySubType' => 'Detached',
                    'PublicRemarks' => null, // simulate missing remarks
                    'TransactionType' => null, // simulate missing
                ]],
            ], 200);
        },
        'idx.example/odata/Media*' => Http::response(['value' => []], 200),
    ]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    Volt::test('admin.feeds.index')->call('importBoth');

    /** @var Listing|null $listing */
    $listing = Listing::query()->where('external_id', 'KCRIT1')->first();
    expect($listing)->not->toBeNull();

    // Critical fields must not be null
    expect($listing->listing_key)->toBe('KCRIT1');
    expect($listing->transaction_type)->toBeString()->not->toBeNull()->toBe('For Sale');
    expect($listing->availability)->toBe('Available');
    expect($listing->public_remarks)->toBeString();
});
