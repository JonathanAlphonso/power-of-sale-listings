<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

test('import both imports five listings with images and pages are reachable', function (): void {
    // Configure services and media handling
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');
    config()->set('services.vow.base_uri', 'https://idx.example/odata/');
    config()->set('services.vow.token', 'test-token');
    config()->set('media.auto_download', true);

    // Fake storage for downloaded images
    Storage::fake('public');

    // Prepare five deterministic property payloads
    $keys = ['K1', 'K2', 'K3', 'K4', 'K5'];
    $properties = array_map(function (string $k, int $n) {
        return [
            'ListingKey' => $k,
            'ListingId' => 'A10'.$n,
            'OriginatingSystemName' => 'TRREB',
            'City' => 'Toronto',
            'StateOrProvince' => 'ON',
            'UnparsedAddress' => "$n King St W, Toronto, ON",
            'StreetNumber' => (string) $n,
            'StreetName' => 'King',
            'StreetSuffix' => 'St W',
            'StandardStatus' => 'Active',
            'ListPrice' => 900000 + ($n * 1000),
            'ModificationTimestamp' => now()->toISOString(),
            'PropertyType' => 'Residential Freehold',
            'PropertySubType' => 'Detached',
            'PublicRemarks' => 'Power of Sale',
            'TransactionType' => 'For Sale',
        ];
    }, $keys, range(1, 5));

    // Fake IDX + VOW Property and Media responses, plus image downloads
    Http::fake([
        // Property listing pages: serve five on first page, then empty
        'idx.example/odata/Property*' => function ($request) use ($properties) {
            $query = [];
            parse_str((string) parse_url((string) $request->url(), PHP_URL_QUERY), $query);
            $skip = (int) ($query['$skip'] ?? 0);

            if ($skip > 0) {
                return Http::response(['value' => []], 200);
            }

            return Http::response(['value' => $properties], 200);
        },
        // Media lookups: return a single Large image per listing key from the filter value
        'idx.example/odata/Media*' => function ($request) {
            $query = [];
            parse_str((string) parse_url((string) $request->url(), PHP_URL_QUERY), $query);
            $filter = (string) ($query['$filter'] ?? '');
            $key = 'KX';
            if (preg_match("/ResourceRecordKey eq '([^']+)'/", $filter, $m) === 1) {
                $key = $m[1];
            }

            return Http::response([
                'value' => [[
                    'MediaURL' => "https://cdn.example/media/{$key}-large.jpg",
                    'MediaType' => 'image/jpeg',
                    'ResourceName' => 'Property',
                    'ResourceRecordKey' => $key,
                    'MediaModificationTimestamp' => now()->toISOString(),
                    'ImageSizeDescription' => 'Large',
                    'LongDescription' => 'Front exterior',
                    'ShortDescription' => 'Exterior',
                    'MediaKey' => 'M'.$key,
                    'MediaCategory' => 'Photo',
                    'MediaStatus' => 'Active',
                ]],
            ], 200);
        },
        // Image download
        'cdn.example/media/*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    // Admin context for Livewire action
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    // Trigger the combined import + media flow via the Volt component
    Volt::test('admin.feeds.index')->call('importBoth');

    // 1) Listings imported
    $listings = Listing::query()->whereIn('external_id', $keys)->orderBy('id')->get();
    expect($listings)->toHaveCount(5);

    // 2) Media records created and 3) images downloaded to storage
    foreach ($listings as $listing) {
        $media = $listing->media()->orderBy('position')->get();
        expect($media->count())->toBeGreaterThan(0);
        $first = $media->first();
        expect($first->stored_path)->not->toBeNull();
        Storage::disk('public')->assertExists((string) $first->stored_path);
    }

    // 4) Public listings page shows cards with images
    $response = $this->get(route('listings.index'));
    $response->assertOk();
    $html = (string) $response->getContent();
    // Expect at least five image tags pointing at the storage path
    expect(substr_count($html, '/storage/'))->toBeGreaterThanOrEqual(5);

    // 5) Each listing show page is reachable, then clean up
    foreach ($listings as $listing) {
        $this->get(route('listings.show', $listing))->assertOk();
    }

    // Cleanup: remove the inserted records (cascade deletes media)
    Listing::query()->whereIn('id', $listings->pluck('id')->all())->delete();
    expect(Listing::query()->whereIn('external_id', $keys)->count())->toBe(0);
});
