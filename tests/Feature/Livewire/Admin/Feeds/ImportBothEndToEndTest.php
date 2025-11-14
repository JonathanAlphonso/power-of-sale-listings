<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\ReplicationCursor;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

it('resets cursors and imports even when prior runs advanced the cursor', function (): void {
    // Run chain synchronously so this test is deterministic
    config()->set('queue.default', 'sync');

    // Point IDX/VOW to the same fake host
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');
    config()->set('services.vow.base_uri', 'https://idx.example/odata/');
    config()->set('services.vow.token', 'test-token');

    // Seed cursors far in the future to simulate a prior bad run
    ReplicationCursor::query()->updateOrCreate(
        ['channel' => 'idx.property.pos'],
        ['last_timestamp' => now()->addDays(3), 'last_key' => 'FUTURE'],
    );
    ReplicationCursor::query()->updateOrCreate(
        ['channel' => 'vow.property.pos'],
        ['last_timestamp' => now()->addDays(3), 'last_key' => 'FUTURE'],
    );

    // Fake Property endpoint:
    // - When the cursor filter is present (contains "ModificationTimestamp"), return 0 items
    // - Fallback request (without the cursor predicate) returns one PoS item
    Http::fake([
        'idx.example/odata/Property*' => function ($request) {
            $query = [];
            parse_str((string) parse_url((string) $request->url(), PHP_URL_QUERY), $query);
            $filter = (string) ($query['$filter'] ?? '');

            $hasCursor = str_contains($filter, 'ModificationTimestamp');

            if ($hasCursor) {
                return Http::response(['value' => []], 200);
            }

            return Http::response([
                'value' => [[
                    'ListingKey' => 'KBOTH1',
                    'ListingId' => 'X123',
                    'OriginatingSystemName' => 'TRREB',
                    'City' => 'Toronto',
                    'StateOrProvince' => 'ON',
                    'UnparsedAddress' => '1 King St W, Toronto, ON',
                    'StreetNumber' => '1',
                    'StreetName' => 'King',
                    'StandardStatus' => 'Active',
                    'ListPrice' => 1000000,
                    'ModificationTimestamp' => now()->toIso8601String(),
                    'PropertyType' => 'Residential Freehold',
                    'PropertySubType' => 'Detached',
                    'PublicRemarks' => 'Power of Sale present',
                    'TransactionType' => 'For Sale',
                ]],
            ], 200);
        },
        // Media can be empty for this test focus
        'idx.example/odata/Media*' => Http::response(['value' => []], 200),
    ]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    // Execute the action from the component (this dispatches the chain)
    Volt::test('admin.feeds.index')->call('importBoth');

    // Assert a listing was created and critical fields are populated
    /** @var Listing|null $listing */
    $listing = Listing::query()->where('external_id', 'KBOTH1')->first();
    expect($listing)->not->toBeNull();
    expect($listing->listing_key)->toBe('KBOTH1');
    expect($listing->transaction_type)->toBe('For Sale');
    expect($listing->availability)->toBe('Available');
    expect($listing->public_remarks)->toBeString();

    // Cursor should not end up in the future after the run
    $idxCursor = ReplicationCursor::query()->where('channel', 'idx.property.pos')->first();
    expect($idxCursor)->not->toBeNull();
    expect($idxCursor->last_timestamp->greaterThan(now()))->toBeFalse();
});
