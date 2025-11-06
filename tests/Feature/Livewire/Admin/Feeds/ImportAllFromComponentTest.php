<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

test('import all button imports listings and marks progress completed', function (): void {
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');

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

    Http::fake([
        'idx.example/odata/Property*' => Http::response(['value' => []], 200),
    ]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    Volt::test('admin.feeds.index')->call('importAllPowerOfSale');

    $progress = (array) Cache::get('idx.import.pos');
    expect($progress['status'] ?? null)->toBe('completed');
    expect((int) ($progress['items_total'] ?? 0))->toBe(0);
});
