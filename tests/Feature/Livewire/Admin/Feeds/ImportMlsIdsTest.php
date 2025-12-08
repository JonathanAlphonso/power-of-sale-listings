<?php

declare(strict_types=1);

use App\Jobs\ImportMlsListings;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

test('import mls ids parses and queues listings from textarea input', function (): void {
    Bus::fake();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    $mlsInput = "X1234567\nW5678901\nC9012345";

    Volt::test('admin.feeds.index')
        ->set('mlsInput', $mlsInput)
        ->call('importMlsIds')
        ->assertSet('mlsInput', '')
        ->assertSet('notice', '3 MLS IDs queued for import.');

    Bus::assertDispatched(ImportMlsListings::class, function (ImportMlsListings $job) {
        return $job->mlsNumbers === ['X1234567', 'W5678901', 'C9012345'];
    });
});

test('import mls ids parses comma separated input', function (): void {
    Bus::fake();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    $mlsInput = 'X1234567,W5678901,C9012345';

    Volt::test('admin.feeds.index')
        ->set('mlsInput', $mlsInput)
        ->call('importMlsIds');

    Bus::assertDispatched(ImportMlsListings::class, function (ImportMlsListings $job) {
        return $job->mlsNumbers === ['X1234567', 'W5678901', 'C9012345'];
    });
});

test('import mls ids parses semicolon separated input', function (): void {
    Bus::fake();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    $mlsInput = 'X1234567;W5678901;C9012345';

    Volt::test('admin.feeds.index')
        ->set('mlsInput', $mlsInput)
        ->call('importMlsIds');

    Bus::assertDispatched(ImportMlsListings::class, function (ImportMlsListings $job) {
        return $job->mlsNumbers === ['X1234567', 'W5678901', 'C9012345'];
    });
});

test('import mls ids removes duplicates', function (): void {
    Bus::fake();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    $mlsInput = "X1234567\nW5678901\nX1234567\nC9012345\nW5678901";

    Volt::test('admin.feeds.index')
        ->set('mlsInput', $mlsInput)
        ->call('importMlsIds')
        ->assertSet('notice', '3 MLS IDs queued for import.');

    Bus::assertDispatched(ImportMlsListings::class, function (ImportMlsListings $job) {
        return count($job->mlsNumbers) === 3
            && in_array('X1234567', $job->mlsNumbers, true)
            && in_array('W5678901', $job->mlsNumbers, true)
            && in_array('C9012345', $job->mlsNumbers, true);
    });
});

test('import mls ids shows error when no valid ids provided', function (): void {
    Bus::fake();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    Volt::test('admin.feeds.index')
        ->set('mlsInput', '')
        ->call('importMlsIds')
        ->assertSet('notice', 'No valid MLS IDs provided.');

    Bus::assertNotDispatched(ImportMlsListings::class);
});

test('import mls ids rejects more than 500 ids', function (): void {
    Bus::fake();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    $mlsInput = implode("\n", array_map(fn ($i) => "MLS{$i}", range(1, 501)));

    Volt::test('admin.feeds.index')
        ->set('mlsInput', $mlsInput)
        ->call('importMlsIds')
        ->assertSet('notice', 'Maximum 500 MLS IDs allowed per import.');

    Bus::assertNotDispatched(ImportMlsListings::class);
});

test('import mls ids trims whitespace from each line', function (): void {
    Bus::fake();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    $mlsInput = "  X1234567  \n  W5678901\nC9012345  ";

    Volt::test('admin.feeds.index')
        ->set('mlsInput', $mlsInput)
        ->call('importMlsIds');

    Bus::assertDispatched(ImportMlsListings::class, function (ImportMlsListings $job) {
        return $job->mlsNumbers === ['X1234567', 'W5678901', 'C9012345'];
    });
});

test('import mls ids handles mixed separators', function (): void {
    Bus::fake();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    $mlsInput = "X1234567\nW5678901,C9012345;E4567890";

    Volt::test('admin.feeds.index')
        ->set('mlsInput', $mlsInput)
        ->call('importMlsIds');

    Bus::assertDispatched(ImportMlsListings::class, function (ImportMlsListings $job) {
        return count($job->mlsNumbers) === 4
            && in_array('X1234567', $job->mlsNumbers, true)
            && in_array('W5678901', $job->mlsNumbers, true)
            && in_array('C9012345', $job->mlsNumbers, true)
            && in_array('E4567890', $job->mlsNumbers, true);
    });
});

test('import mls ids filters empty lines', function (): void {
    Bus::fake();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    $mlsInput = "X1234567\n\n\nW5678901\n\nC9012345\n";

    Volt::test('admin.feeds.index')
        ->set('mlsInput', $mlsInput)
        ->call('importMlsIds');

    Bus::assertDispatched(ImportMlsListings::class, function (ImportMlsListings $job) {
        return $job->mlsNumbers === ['X1234567', 'W5678901', 'C9012345'];
    });
});

test('import mls listings job creates listings in database', function (): void {
    Http::fake([
        '*Property*' => Http::response([
            '@odata.context' => '$metadata#Property',
            'value' => [
                [
                    'ListingKey' => 'X1234567',
                    'ListingId' => 'X1234567',
                    'OriginatingSystemName' => 'TRREB',
                    'StandardStatus' => 'Active',
                    'ContractStatus' => 'Available',
                    'PropertyType' => 'Residential Freehold',
                    'PropertySubType' => 'Detached',
                    'StreetNumber' => '123',
                    'StreetName' => 'Main',
                    'StreetSuffix' => 'Street',
                    'City' => 'Toronto',
                    'StateOrProvince' => 'ON',
                    'PostalCode' => 'M5V 1A1',
                    'ListPrice' => 999000.0,
                    'BedroomsTotal' => 3,
                    'BathroomsTotalInteger' => 2,
                    'PublicRemarks' => 'Power of Sale - Great opportunity!',
                    'ModificationTimestamp' => '2025-01-15T10:00:00Z',
                    'TransactionType' => 'For Sale',
                ],
                [
                    'ListingKey' => 'W5678901',
                    'ListingId' => 'W5678901',
                    'OriginatingSystemName' => 'TRREB',
                    'StandardStatus' => 'Active',
                    'ContractStatus' => 'Available',
                    'PropertyType' => 'Residential Freehold',
                    'PropertySubType' => 'Semi-Detached',
                    'StreetNumber' => '456',
                    'StreetName' => 'Oak',
                    'StreetSuffix' => 'Avenue',
                    'City' => 'Mississauga',
                    'StateOrProvince' => 'ON',
                    'PostalCode' => 'L5B 2C3',
                    'ListPrice' => 750000.0,
                    'BedroomsTotal' => 4,
                    'BathroomsTotalInteger' => 3,
                    'PublicRemarks' => 'Sold under Power of Sale.',
                    'ModificationTimestamp' => '2025-01-14T09:30:00Z',
                    'TransactionType' => 'For Sale',
                ],
            ],
        ]),
        '*Media*' => Http::response(['value' => []]),
    ]);

    $job = new ImportMlsListings(['X1234567', 'W5678901', 'C9012345']);
    $job->handle();

    // Verify listings were created
    expect(Listing::where('listing_key', 'X1234567')->exists())->toBeTrue();
    expect(Listing::where('listing_key', 'W5678901')->exists())->toBeTrue();

    // C9012345 should not exist as it wasn't in the API response
    expect(Listing::where('listing_key', 'C9012345')->exists())->toBeFalse();

    // Verify listing data
    $listing1 = Listing::where('listing_key', 'X1234567')->first();
    expect($listing1->city)->toBe('Toronto');
    expect((float) $listing1->list_price)->toBe(999000.0);
    expect($listing1->bedrooms)->toBe(3);
    expect($listing1->display_status)->toBe('Active');

    $listing2 = Listing::where('listing_key', 'W5678901')->first();
    expect($listing2->city)->toBe('Mississauga');
    expect((float) $listing2->list_price)->toBe(750000.0);
    expect($listing2->bedrooms)->toBe(4);
});

test('import mls listings job uses ListingKey for API filter', function (): void {
    Http::fake([
        '*Property*' => Http::response([
            '@odata.context' => '$metadata#Property',
            'value' => [],
        ]),
    ]);

    $job = new ImportMlsListings(['X1234567', 'W5678901']);
    $job->handle();

    // Verify the correct filter was used (ListingKey, not ListingId)
    Http::assertSent(function ($request) {
        $filter = $request->data()['$filter'] ?? '';

        return str_contains($filter, "ListingKey eq 'X1234567'")
            && str_contains($filter, "ListingKey eq 'W5678901'")
            && ! str_contains($filter, 'ListingId');
    });
});
