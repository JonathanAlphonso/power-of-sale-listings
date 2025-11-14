<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

test('import stores full public remarks without truncation', function (): void {
    config()->set('queue.default', 'sync');
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');
    config()->set('services.vow.base_uri', 'https://idx.example/odata/');
    config()->set('services.vow.token', 'test-token');

    $long = str_repeat('Long remarks ', 600); // ~7200 chars

    Http::fake([
        'idx.example/odata/Property*' => function ($request) use ($long) {
            $query = [];
            parse_str((string) parse_url((string) $request->url(), PHP_URL_QUERY), $query);
            $skip = (int) ($query['$skip'] ?? 0);
            if ($skip > 0) {
                return Http::response(['value' => []], 200);
            }

            return Http::response([
                'value' => [[
                    'ListingKey' => 'KFULL1',
                    'ListingId' => 'FULL-1',
                    'OriginatingSystemName' => 'TRREB',
                    'City' => 'Toronto',
                    'StateOrProvince' => 'ON',
                    'UnparsedAddress' => '1 Test Ave, Toronto, ON',
                    'StreetNumber' => '1',
                    'StreetName' => 'Test',
                    'StandardStatus' => 'Active',
                    'ListPrice' => 100,
                    'ModificationTimestamp' => now()->toIso8601String(),
                    'PropertyType' => 'Residential Freehold',
                    'PropertySubType' => 'Detached',
                    'PublicRemarks' => $long,
                    'TransactionType' => 'For Sale',
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
    $listing = Listing::query()->where('external_id', 'KFULL1')->first();
    expect($listing)->not->toBeNull();
    expect(strlen((string) $listing->public_remarks_full))->toBeGreaterThan(6000);
});
