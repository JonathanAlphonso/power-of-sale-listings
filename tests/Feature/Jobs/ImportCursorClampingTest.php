<?php

declare(strict_types=1);

use App\Jobs\ImportIdxPowerOfSale;
use App\Models\ReplicationCursor;
use Illuminate\Support\Facades\Http;

test('replication cursor never advances into the future', function (): void {
    config()->set('queue.default', 'sync');
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');

    // Seed/reset cursor to epoch
    /** @var ReplicationCursor $cursor */
    $cursor = ReplicationCursor::query()->firstOrCreate([
        'channel' => 'idx.property.pos',
    ], [
        'last_timestamp' => now()->startOfDay()->subYears(50),
        'last_key' => '0',
    ]);

    // Fake a single page with a future ModificationTimestamp
    $future = now()->addDays(2)->toIso8601String();

    Http::fake([
        'idx.example/odata/Property*' => Http::response([
            'value' => [[
                'ListingKey' => 'FUTURE-1',
                'ListingId' => 'F1',
                'OriginatingSystemName' => 'TRREB',
                'City' => 'Toronto',
                'StateOrProvince' => 'ON',
                'UnparsedAddress' => '1 Future St, Toronto, ON',
                'StandardStatus' => 'Active',
                'ListPrice' => 1,
                'ModificationTimestamp' => $future,
                'PropertyType' => 'Residential',
                'PropertySubType' => 'Detached',
                'PublicRemarks' => 'Power of Sale',
                'TransactionType' => 'For Sale',
            ]],
        ], 200),
        // Media lookups (if any) return empty
        'idx.example/odata/Media*' => Http::response(['value' => []], 200),
    ]);

    (new ImportIdxPowerOfSale(pageSize: 50, maxPages: 1))->handle(app(\App\Services\Idx\IdxClient::class));

    $cursor->refresh();

    // Cursor should be clamped to <= now()
    expect($cursor->last_timestamp)->not->toBeNull();
    expect($cursor->last_timestamp->greaterThan(now()))->toBeFalse();
});
