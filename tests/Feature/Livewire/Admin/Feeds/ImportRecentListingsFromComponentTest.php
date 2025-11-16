<?php

declare(strict_types=1);

use App\Jobs\ImportRecentListings;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

test('import recent listings button queues job and sets notice', function (): void {
    Bus::fake();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    Volt::test('admin.feeds.index')
        ->call('importRecentListings')
        ->assertSet('notice', __('Recent (24h) import queued'));

    Bus::assertDispatched(ImportRecentListings::class, function (ImportRecentListings $job): bool {
        return $job->pageSize === 50 && $job->maxPages === 200;
    });
});

test('import recent listings imports idx and vow records from last 24 hours', function (): void {
    config()->set('queue.default', 'sync');

    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');
    config()->set('services.vow.base_uri', 'https://vow.example/odata/');
    config()->set('services.vow.token', 'test-token');

    Http::fake([
        'idx.example/odata/Property*' => Http::response([
            'value' => [[
                'ListingKey' => 'IDX1',
                'ListingId' => 'IDX-100',
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
                'PublicRemarks' => 'Standard listing',
                'TransactionType' => 'For Sale',
            ]],
        ], 200),
        'vow.example/odata/Property*' => Http::response([
            'value' => [[
                'ListingKey' => 'VOW1',
                'ListingId' => 'VOW-200',
                'OriginatingSystemName' => 'TRREB',
                'City' => 'Mississauga',
                'StateOrProvince' => 'ON',
                'UnparsedAddress' => '100 Main St, Mississauga, ON',
                'StreetNumber' => '100',
                'StreetName' => 'Main',
                'StreetSuffix' => 'St',
                'StandardStatus' => 'Active',
                'ListPrice' => 750000,
                'ModificationTimestamp' => now()->toISOString(),
                'PropertyType' => 'Residential Freehold',
                'PropertySubType' => 'Semi-Detached',
                'PublicRemarks' => 'Recent listing',
                'TransactionType' => 'For Sale',
            ]],
        ], 200),
    ]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    Volt::test('admin.feeds.index')->call('importRecentListings');

    /** @var Listing|null $idxListing */
    $idxListing = Listing::query()->where('external_id', 'IDX1')->first();
    expect($idxListing)->not->toBeNull();

    /** @var Listing|null $vowListing */
    $vowListing = Listing::query()->where('external_id', 'VOW1')->first();
    expect($vowListing)->not->toBeNull();
});

test('import recent listings uses timestamp and key cursor replication without relying on next links', function (): void {
    config()->set('queue.default', 'sync');

    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');
    config()->set('services.vow.base_uri', 'https://vow.example/odata/');
    config()->set('services.vow.token', 'test-token');

    Queue::fake(); // Ignore SyncIdxMediaForListing side jobs

    $now = now()->startOfMinute();

    Http::fake([
        // IDX: one count request, then two data batches, then empty
        'idx.example/odata/Property*' => function ($request) use ($now) {
            $query = [];
            parse_str((string) parse_url((string) $request->url(), PHP_URL_QUERY), $query);

            if (isset($query['$count']) && $query['$count'] === 'true') {
                return Http::response([
                    '@odata.count' => 3,
                    'value' => [],
                ], 200);
            }

            static $idxPage = 0;
            $idxPage++;

            if ($idxPage === 1) {
                return Http::response([
                    'value' => [
                        [
                            'ListingKey' => 'IDX1',
                            'ListingId' => 'IDX-100',
                            'OriginatingSystemName' => 'TRREB',
                            'City' => 'Toronto',
                            'StateOrProvince' => 'ON',
                            'UnparsedAddress' => '1 King St W, Toronto, ON',
                            'StreetNumber' => '1',
                            'StreetName' => 'King',
                            'StreetSuffix' => 'St W',
                            'StandardStatus' => 'Active',
                            'ListPrice' => 1000000,
                            'ModificationTimestamp' => $now->copy()->subMinutes(10)->toIso8601String(),
                            'PropertyType' => 'Residential Freehold',
                            'PropertySubType' => 'Detached',
                            'PublicRemarks' => 'Recent IDX listing 1',
                            'TransactionType' => 'For Sale',
                        ],
                        [
                            'ListingKey' => 'IDX2',
                            'ListingId' => 'IDX-101',
                            'OriginatingSystemName' => 'TRREB',
                            'City' => 'Toronto',
                            'StateOrProvince' => 'ON',
                            'UnparsedAddress' => '2 King St W, Toronto, ON',
                            'StreetNumber' => '2',
                            'StreetName' => 'King',
                            'StreetSuffix' => 'St W',
                            'StandardStatus' => 'Active',
                            'ListPrice' => 1100000,
                            'ModificationTimestamp' => $now->copy()->subMinutes(9)->toIso8601String(),
                            'PropertyType' => 'Residential Freehold',
                            'PropertySubType' => 'Detached',
                            'PublicRemarks' => 'Recent IDX listing 2',
                            'TransactionType' => 'For Sale',
                        ],
                    ],
                ], 200);
            }

            if ($idxPage === 2) {
                return Http::response([
                    'value' => [
                        [
                            'ListingKey' => 'IDX3',
                            'ListingId' => 'IDX-102',
                            'OriginatingSystemName' => 'TRREB',
                            'City' => 'Toronto',
                            'StateOrProvince' => 'ON',
                            'UnparsedAddress' => '3 King St W, Toronto, ON',
                            'StreetNumber' => '3',
                            'StreetName' => 'King',
                            'StreetSuffix' => 'St W',
                            'StandardStatus' => 'Active',
                            'ListPrice' => 1200000,
                            'ModificationTimestamp' => $now->copy()->subMinutes(8)->toIso8601String(),
                            'PropertyType' => 'Residential Freehold',
                            'PropertySubType' => 'Detached',
                            'PublicRemarks' => 'Recent IDX listing 3',
                            'TransactionType' => 'For Sale',
                        ],
                    ],
                ], 200);
            }

            return Http::response(['value' => []], 200);
        },

        // VOW: one count request then one data batch
        'vow.example/odata/Property*' => function ($request) use ($now) {
            $query = [];
            parse_str((string) parse_url((string) $request->url(), PHP_URL_QUERY), $query);

            if (isset($query['$count']) && $query['$count'] === 'true') {
                return Http::response([
                    '@odata.count' => 1,
                    'value' => [],
                ], 200);
            }

            static $vowPage = 0;
            $vowPage++;

            if ($vowPage === 1) {
                return Http::response([
                    'value' => [
                        [
                            'ListingKey' => 'VOW1',
                            'ListingId' => 'VOW-200',
                            'OriginatingSystemName' => 'TRREB',
                            'City' => 'Mississauga',
                            'StateOrProvince' => 'ON',
                            'UnparsedAddress' => '100 Main St, Mississauga, ON',
                            'StreetNumber' => '100',
                            'StreetName' => 'Main',
                            'StreetSuffix' => 'St',
                            'StandardStatus' => 'Active',
                            'ListPrice' => 750000,
                            'ModificationTimestamp' => $now->copy()->subMinutes(7)->toIso8601String(),
                            'PropertyType' => 'Residential Freehold',
                            'PropertySubType' => 'Semi-Detached',
                            'PublicRemarks' => 'Recent VOW listing',
                            'TransactionType' => 'For Sale',
                        ],
                    ],
                ], 200);
            }

            return Http::response(['value' => []], 200);
        },
    ]);

    $job = new ImportRecentListings(pageSize: 2, maxPages: 10);
    $job->handle(app(\App\Services\Idx\IdxClient::class));

    expect(Listing::query()->whereIn('external_id', ['IDX1', 'IDX2', 'IDX3', 'VOW1'])->count())->toBe(4);

    Http::assertSentCount(5); // 1 IDX count + 2 IDX batches + 1 VOW count + 1 VOW batch
});
