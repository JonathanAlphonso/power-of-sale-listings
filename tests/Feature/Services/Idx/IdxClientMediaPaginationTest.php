<?php

declare(strict_types=1);

use App\Services\Idx\IdxClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');
});

it('fetches media page with cursor-based pagination', function (): void {
    Http::fake([
        'idx.example/odata/Media*' => Http::response([
            'value' => [
                [
                    'MediaKey' => 'media-1',
                    'ResourceRecordKey' => 'listing-a',
                    'MediaURL' => 'https://example.com/photo1.jpg',
                    'MediaType' => 'image/jpeg',
                    'ImageSizeDescription' => 'Large',
                    'MediaModificationTimestamp' => '2024-01-15T10:00:00Z',
                    'MediaCategory' => 'Photo',
                    'MediaStatus' => 'Active',
                    'ResourceName' => 'Property',
                ],
                [
                    'MediaKey' => 'media-2',
                    'ResourceRecordKey' => 'listing-b',
                    'MediaURL' => 'https://example.com/photo2.jpg',
                    'MediaType' => 'image/jpeg',
                    'ImageSizeDescription' => 'Large',
                    'MediaModificationTimestamp' => '2024-01-15T11:00:00Z',
                    'MediaCategory' => 'Photo',
                    'MediaStatus' => 'Active',
                    'ResourceName' => 'Property',
                ],
            ],
        ], 200),
    ]);

    /** @var IdxClient $client */
    $client = app(IdxClient::class);
    $result = $client->fetchMediaPage(null, null, 10);

    expect($result)->toHaveKey('items');
    expect($result)->toHaveKey('cursor');
    expect($result)->toHaveKey('has_more');

    expect($result['items'])->toHaveCount(2);
    expect($result['items'][0]['media_key'])->toBe('media-1');
    expect($result['items'][0]['resource_key'])->toBe('listing-a');
    expect($result['items'][1]['media_key'])->toBe('media-2');

    expect($result['cursor']['timestamp'])->toBe('2024-01-15T11:00:00Z');
    expect($result['cursor']['media_key'])->toBe('media-2');
    expect($result['has_more'])->toBeFalse(); // Less than limit
});

it('uses cursor timestamp and media key in filter', function (): void {
    Http::fake([
        'idx.example/odata/Media*' => Http::response(['value' => []], 200),
    ]);

    /** @var IdxClient $client */
    $client = app(IdxClient::class);
    $client->fetchMediaPage('2024-01-15T10:00:00Z', 'media-cursor-123', 50);

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), '/Media')) {
            return false;
        }

        $queryString = parse_url($request->url(), PHP_URL_QUERY) ?: '';
        parse_str((string) $queryString, $query);

        $filter = (string) ($query['$filter'] ?? '');

        // Should use cursor-based filter
        expect($filter)->toContain('MediaModificationTimestamp gt 2024-01-15T10:00:00Z');
        expect($filter)->toContain("MediaModificationTimestamp eq 2024-01-15T10:00:00Z and MediaKey gt 'media-cursor-123'");

        // Should use proper ordering
        expect($query['$orderby'] ?? '')->toBe('MediaModificationTimestamp,MediaKey');

        return true;
    });
});

it('indicates has_more when page is full', function (): void {
    // Return exactly the limit number of items
    $items = [];
    for ($i = 1; $i <= 5; $i++) {
        $items[] = [
            'MediaKey' => "media-{$i}",
            'ResourceRecordKey' => "listing-{$i}",
            'MediaURL' => "https://example.com/photo{$i}.jpg",
            'MediaType' => 'image/jpeg',
            'ImageSizeDescription' => 'Large',
            'MediaModificationTimestamp' => "2024-01-15T1{$i}:00:00Z",
            'MediaCategory' => 'Photo',
            'MediaStatus' => 'Active',
            'ResourceName' => 'Property',
        ];
    }

    Http::fake([
        'idx.example/odata/Media*' => Http::response(['value' => $items], 200),
    ]);

    /** @var IdxClient $client */
    $client = app(IdxClient::class);
    $result = $client->fetchMediaPage(null, null, 5);

    expect($result['has_more'])->toBeTrue();
    expect($result['items'])->toHaveCount(5);
});

it('deduplicates listing keys when getting media changes', function (): void {
    // Return multiple media items for the same listing
    Http::fake([
        'idx.example/odata/Media*' => Http::response([
            'value' => [
                [
                    'MediaKey' => 'media-1',
                    'ResourceRecordKey' => 'listing-a', // Duplicate
                    'MediaURL' => 'https://example.com/photo1.jpg',
                    'MediaType' => 'image/jpeg',
                    'ImageSizeDescription' => 'Large',
                    'MediaModificationTimestamp' => '2024-01-15T10:00:00Z',
                    'MediaCategory' => 'Photo',
                    'MediaStatus' => 'Active',
                    'ResourceName' => 'Property',
                ],
                [
                    'MediaKey' => 'media-2',
                    'ResourceRecordKey' => 'listing-a', // Duplicate
                    'MediaURL' => 'https://example.com/photo2.jpg',
                    'MediaType' => 'image/jpeg',
                    'ImageSizeDescription' => 'Large',
                    'MediaModificationTimestamp' => '2024-01-15T10:01:00Z',
                    'MediaCategory' => 'Photo',
                    'MediaStatus' => 'Active',
                    'ResourceName' => 'Property',
                ],
                [
                    'MediaKey' => 'media-3',
                    'ResourceRecordKey' => 'listing-b', // Different listing
                    'MediaURL' => 'https://example.com/photo3.jpg',
                    'MediaType' => 'image/jpeg',
                    'ImageSizeDescription' => 'Large',
                    'MediaModificationTimestamp' => '2024-01-15T10:02:00Z',
                    'MediaCategory' => 'Photo',
                    'MediaStatus' => 'Active',
                    'ResourceName' => 'Property',
                ],
            ],
        ], 200),
    ]);

    /** @var IdxClient $client */
    $client = app(IdxClient::class);
    $keys = $client->getListingKeysWithMediaChanges(null, 10);

    // Should return unique listing keys only
    expect($keys)->toHaveCount(2);
    expect($keys)->toContain('listing-a');
    expect($keys)->toContain('listing-b');
});

it('returns empty results when disabled', function (): void {
    config()->set('services.idx.base_uri', '');
    config()->set('services.idx.token', '');

    /** @var IdxClient $client */
    $client = app(IdxClient::class);

    $pageResult = $client->fetchMediaPage();
    expect($pageResult['items'])->toBeEmpty();
    expect($pageResult['has_more'])->toBeFalse();

    $keysResult = $client->getListingKeysWithMediaChanges();
    expect($keysResult)->toBeEmpty();
});

it('handles API errors gracefully', function (): void {
    Http::fake([
        'idx.example/odata/Media*' => Http::response(['error' => 'Server error'], 500),
    ]);

    /** @var IdxClient $client */
    $client = app(IdxClient::class);
    $result = $client->fetchMediaPage();

    expect($result['items'])->toBeEmpty();
    expect($result['cursor']['timestamp'])->toBeNull();
    expect($result['cursor']['media_key'])->toBeNull();
    expect($result['has_more'])->toBeFalse();
});
