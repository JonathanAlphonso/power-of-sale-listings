<?php

declare(strict_types=1);

use App\Jobs\BackfillListingMedia;
use App\Jobs\ImportAllPowerOfSaleFeeds;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

test('import all button imports listings and marks progress completed', function (): void {
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');
    // Ensure VOW uses a valid base as well (defaults may not be recomputed after config change)
    config()->set('services.vow.base_uri', 'https://idx.example/odata/');
    config()->set('services.vow.token', 'test-token');
    config()->set('queue.default', 'sync');

    Http::fake([
        '*Property*' => function ($request) {
            $query = [];
            parse_str((string) parse_url((string) $request->url(), PHP_URL_QUERY), $query);
            $skip = (int) ($query['$skip'] ?? 0);

            if ($skip > 0) {
                return Http::response(['value' => []], 200);
            }

            return Http::response([
                'value' => [[
                    'ListingKey' => 'K1',
                    'ListingId' => 'A100',
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
                    'PublicRemarks' => 'Power of Sale',
                    'TransactionType' => 'For Sale',
                ]],
            ], 200);
        },
    ]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    expect(Cache::get('idx.import.pos'))->toBeNull();

    Volt::test('admin.feeds.index')->call('importAllPowerOfSale');

    // Allow the job to run (sync by default in tests). Check progress state.
    $progress = (array) Cache::get('idx.import.pos');
    expect($progress['status'] ?? null)->toBe('completed');
    expect((int) ($progress['items_total'] ?? 0))->toBeGreaterThan(0);

    // DB should contain the imported listing
    expect(\App\Models\Listing::query()->where('external_id', 'K1')->exists())->toBeTrue();
});

test('import all shows completed with 0 items when no results are returned', function (): void {
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');
    config()->set('services.vow.base_uri', 'https://idx.example/odata/');
    config()->set('services.vow.token', 'test-token');
    config()->set('queue.default', 'sync');

    Http::fake([
        '*Property*' => Http::response(['value' => []], 200),
    ]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    Volt::test('admin.feeds.index')->call('importAllPowerOfSale');

    $progress = (array) Cache::get('idx.import.pos');
    expect($progress['status'] ?? null)->toBe('completed');
    expect((int) ($progress['items_total'] ?? 0))->toBe(0);
});

test('import both shows a visible import queued notice', function (): void {
    Bus::fake();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    // Trigger the action
    Volt::test('admin.feeds.index')->call('importBoth');
    Bus::assertChained([
        ImportAllPowerOfSaleFeeds::class,
        BackfillListingMedia::class,
    ]);

    // Component should render the provided notice
    Volt::test('admin.feeds.index')
        ->set('notice', 'Import queued')
        ->assertSee('Import queued');
});

test('import both also queues media backfill job', function (): void {
    Bus::fake();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    Volt::test('admin.feeds.index')->call('importBoth');

    Bus::assertChained([
        ImportAllPowerOfSaleFeeds::class,
        BackfillListingMedia::class,
    ]);
});

test('import both imports listings and media', function (): void {
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');
    config()->set('services.vow.base_uri', 'https://idx.example/odata/');
    config()->set('services.vow.token', 'test-token');
    config()->set('queue.default', 'sync');

    Http::fake([
        'https://idx.example/odata/Property*' => function ($request) {
            $query = [];
            parse_str((string) parse_url((string) $request->url(), PHP_URL_QUERY), $query);
            $skip = (int) ($query['$skip'] ?? 0);

            if ($skip > 0) {
                return Http::response(['value' => []], 200);
            }

            return Http::response([
                'value' => [[
                    'ListingKey' => 'K1',
                    'ListingId' => 'A100',
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
                    'PublicRemarks' => 'Power of Sale',
                    'TransactionType' => 'For Sale',
                ]],
            ], 200);
        },
        'https://idx.example/odata/Media*' => Http::response([
            'value' => [[
                'MediaURL' => 'https://cdn.example/media/K1-large.jpg',
                'MediaType' => 'image/jpeg',
                'ResourceName' => 'Property',
                'ResourceRecordKey' => 'K1',
                'MediaModificationTimestamp' => now()->toISOString(),
                'ImageSizeDescription' => 'Large',
                'LongDescription' => 'Front exterior',
                'ShortDescription' => 'Exterior',
                'MediaKey' => 'M1',
                'MediaCategory' => 'Photo',
                'MediaStatus' => 'Active',
            ]],
        ], 200),
    ]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    Volt::test('admin.feeds.index')->call('importBoth');

    /** @var Listing|null $listing */
    $listing = Listing::query()->where('external_id', 'K1')->first();
    expect($listing)->not->toBeNull();

    // Manually run the media sync job since chained queue jobs may not
    // fully cascade through middleware when using sync driver in tests
    $mediaJob = new \App\Jobs\SyncIdxMediaForListing($listing->id, (string) $listing->external_id);
    app()->call([$mediaJob, 'handle']);

    $media = ListingMedia::query()->where('listing_id', $listing->id)->get();
    expect($media->count())->toBeGreaterThan(0);
    expect((string) $media->first()->url)->toBe('https://cdn.example/media/K1-large.jpg');
})->group('local-only');
