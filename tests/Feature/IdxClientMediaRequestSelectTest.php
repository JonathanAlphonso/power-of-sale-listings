<?php

declare(strict_types=1);

use App\Services\Idx\IdxClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('composes Media request with $select and stable $orderby', function (): void {
    config()->set('services.idx.base_uri', 'https://idx.example/odata/');
    config()->set('services.idx.token', 'test-token');

    Cache::flush();

    Http::fake([
        // Property returns one Active listing to trigger a Media lookup
        'idx.example/odata/Property*' => Http::response([
            'value' => [
                [
                    'ListingKey' => 'X1',
                    'StandardStatus' => 'Active',
                    'ModificationTimestamp' => now()->toISOString(),
                ],
            ],
        ], 200),
        'idx.example/odata/Media*' => Http::response(['value' => []], 200),
        '*' => Http::response(['value' => []], 200),
    ]);

    /** @var IdxClient $client */
    $client = app(IdxClient::class);
    $client->fetchListings(1);

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), '/Media')) {
            return false;
        }

        $queryString = parse_url($request->url(), PHP_URL_QUERY) ?: '';
        parse_str((string) $queryString, $query);

        expect($query)->toHaveKey('$select');
        expect($query['$select'])->toBeString();
        expect($query['$select'])->toContain('MediaURL');
        expect($query['$select'])->toContain('MediaModificationTimestamp');
        expect($query)->toHaveKey('$orderby');
        expect($query['$orderby'])->toBe('MediaModificationTimestamp desc');
        expect($query)->toHaveKey('$top');
        expect((int) $query['$top'])->toBe(1);
        expect($query)->toHaveKey('$filter');
        expect((string) $query['$filter'])->toContain("ResourceName eq 'Property'");

        return true;
    });
});
