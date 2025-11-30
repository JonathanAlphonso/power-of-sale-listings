<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Jobs\ImportAllPowerOfSaleFeeds;
use App\Jobs\ImportPosLast30Days;
use App\Models\Listing;
use App\Models\User;
use App\Services\Idx\IdxClient;
use App\Support\ResoFilters;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;

/**
 * Live End-to-End Tests for POS Replication Button
 *
 * These tests hit the REAL IDX/VOW APIs - no mocking.
 * They mirror exactly what happens when an admin clicks
 * "Run POS Replication (Last 30 Days)" in the UI.
 *
 * Uses DatabaseTransactions (configured in Pest.php) to rollback
 * after each test, preserving existing users and data.
 *
 * To run:
 *   php artisan test tests/Feature/Live/PosReplicationLiveE2eTest.php
 *
 * Requirements:
 *   - IDX_BASE_URI and IDX_TOKEN must be set in .env
 *   - VOW_TOKEN should be set for VOW feed tests
 */

beforeEach(function (): void {
    // Clear any stale import state
    Cache::forget('idx.import.pos');
    Cache::forget('idx.import.pos.dispatching');

    // Skip if API credentials not configured
    if (empty(config('services.idx.base_uri')) || empty(config('services.idx.token'))) {
        $this->markTestSkipped('IDX API not configured. Set IDX_BASE_URI and IDX_TOKEN in .env');
    }
});

describe('POS Replication Button - Live API', function (): void {

    it('imports POS listings when admin clicks the button', function (): void {
        // Run synchronously for deterministic testing
        config()->set('queue.default', 'sync');
        // Skip media downloads to speed up test
        config()->set('media.auto_download', false);

        // Create or get admin user
        $admin = User::where('role', UserRole::Admin)->first()
            ?? User::factory()->admin()->create();

        $this->actingAs($admin);
        Volt::actingAs($admin);

        $listingCountBefore = Listing::count();

        // Click the button - exactly as in the UI
        $component = Volt::test('admin.feeds.index');
        $component->call('importBoth');

        // Check the import completed
        $status = Cache::get('idx.import.pos', []);
        expect($status['status'] ?? null)->toBeIn(['completed', 'running', null]);

        // Verify POS listings were imported
        $posListings = Listing::query()
            ->whereNotNull('public_remarks')
            ->where('public_remarks', '!=', '')
            ->get()
            ->filter(fn ($l) => ResoFilters::isPowerOfSaleRemarks($l->public_remarks));

        logger()->info('live_e2e.import_results', [
            'listings_before' => $listingCountBefore,
            'listings_after' => Listing::count(),
            'pos_listings_found' => $posListings->count(),
            'import_status' => $status,
        ]);

        // We should have found some POS listings (API typically has 15-25)
        expect($posListings->count())->toBeGreaterThan(0,
            'Expected to find POS listings from the live API'
        );
    });

    it('populates all required fields from live API data', function (): void {
        config()->set('queue.default', 'sync');
        config()->set('media.auto_download', false);

        /** @var IdxClient $client */
        $client = app(IdxClient::class);

        // Run import with small batch for speed
        (new ImportPosLast30Days(
            pageSize: 50,
            maxPages: 10,
            days: 30,
            feed: 'idx'
        ))->handle($client);

        $listings = Listing::query()
            ->whereNotNull('public_remarks')
            ->where('public_remarks', '!=', '')
            ->limit(20)
            ->get()
            ->filter(fn ($l) => ResoFilters::isPowerOfSaleRemarks($l->public_remarks));

        expect($listings->count())->toBeGreaterThan(0,
            'Expected to import at least one POS listing from live API'
        );

        foreach ($listings as $listing) {
            // Core identifiers
            expect($listing->listing_key)->not->toBeNull();
            expect($listing->mls_number)->not->toBeNull();
            expect($listing->board_code)->not->toBeNull();

            // Transaction type must be For Sale
            expect($listing->transaction_type)->toBe('For Sale');

            // POS keyword verification
            expect(ResoFilters::isPowerOfSaleRemarks($listing->public_remarks))->toBeTrue(
                "Listing {$listing->listing_key} does not have POS keywords"
            );

            // Source tracking
            expect($listing->source_id)->not->toBeNull();

            // Availability status
            expect($listing->availability)->toBeIn(['Available', 'Unavailable']);

            logger()->debug('live_e2e.listing_verified', [
                'listing_key' => $listing->listing_key,
                'city' => $listing->city,
                'list_price' => $listing->list_price,
                'remarks_preview' => substr($listing->public_remarks ?? '', 0, 80),
            ]);
        }
    });

    it('correctly paginates through all available listings', function (): void {
        config()->set('queue.default', 'sync');
        config()->set('media.auto_download', false);

        /** @var IdxClient $client */
        $client = app(IdxClient::class);

        // Run with enough pages to test pagination
        (new ImportPosLast30Days(
            pageSize: 100,
            maxPages: 50,  // Should scan up to 5000 listings
            days: 30,
            feed: 'idx'
        ))->handle($client);

        $status = Cache::get('idx.import.pos', []);

        logger()->info('live_e2e.pagination_test', [
            'items_scanned' => $status['items_scanned'] ?? 0,
            'items_total' => $status['items_total'] ?? 0,
            'pages' => $status['pages'] ?? 0,
        ]);

        // Should have scanned more than one page worth
        $scanned = (int) ($status['items_scanned'] ?? 0);
        expect($scanned)->toBeGreaterThan(100,
            'Expected to scan more than 100 listings (pagination should work)'
        );

        // Verify we found some POS matches
        $matched = (int) ($status['items_total'] ?? 0);
        expect($matched)->toBeGreaterThan(0,
            'Expected to find at least one POS listing after scanning'
        );
    });

    it('imports from both IDX and VOW feeds', function (): void {
        // Skip if VOW not configured
        if (empty(config('services.vow.token'))) {
            $this->markTestSkipped('VOW API not configured. Set VOW_TOKEN in .env');
        }

        config()->set('queue.default', 'sync');
        config()->set('media.auto_download', false);

        /** @var IdxClient $client */
        $client = app(IdxClient::class);

        // Run the combined import (same as button)
        (new ImportAllPowerOfSaleFeeds(
            pageSize: 100,
            maxPages: 20,
            days: 30
        ))->handle($client);

        $status = Cache::get('idx.import.pos', []);
        expect($status['status'] ?? null)->toBe('completed');

        // Verify listings exist with both IDX and VOW sources
        $idxSource = \App\Models\Source::where('slug', 'idx')->first();
        $vowSource = \App\Models\Source::where('slug', 'vow')->first();

        $posListings = Listing::query()
            ->whereNotNull('public_remarks')
            ->get()
            ->filter(fn ($l) => ResoFilters::isPowerOfSaleRemarks($l->public_remarks ?? ''));

        logger()->info('live_e2e.both_feeds_test', [
            'total_pos_listings' => $posListings->count(),
            'idx_source_exists' => $idxSource !== null,
            'vow_source_exists' => $vowSource !== null,
        ]);

        expect($posListings->count())->toBeGreaterThan(0);
    });

    it('handles duplicate imports correctly', function (): void {
        config()->set('queue.default', 'sync');
        config()->set('media.auto_download', false);

        /** @var IdxClient $client */
        $client = app(IdxClient::class);

        // First import
        (new ImportPosLast30Days(
            pageSize: 50,
            maxPages: 5,
            days: 30,
            feed: 'idx'
        ))->handle($client);

        $countAfterFirst = Listing::count();
        $keysAfterFirst = Listing::pluck('listing_key')->toArray();

        // Second import - same data
        (new ImportPosLast30Days(
            pageSize: 50,
            maxPages: 5,
            days: 30,
            feed: 'idx'
        ))->handle($client);

        $countAfterSecond = Listing::count();

        // Should not create duplicates
        expect($countAfterSecond)->toBe($countAfterFirst,
            'Second import should not create duplicate listings'
        );

        // Verify no duplicate listing_keys
        $duplicates = Listing::query()
            ->select('listing_key')
            ->groupBy('listing_key')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        expect($duplicates)->toBe(0, 'Found duplicate listing_keys in database');
    });

    it('syncs media for imported listings', function (): void {
        config()->set('queue.default', 'sync');
        // Allow media sync but skip actual file downloads
        config()->set('media.auto_download', false);

        $admin = User::where('role', UserRole::Admin)->first()
            ?? User::factory()->admin()->create();

        $this->actingAs($admin);
        Volt::actingAs($admin);

        Volt::test('admin.feeds.index')->call('importBoth');

        // Check for listings with media
        $listingsWithMedia = Listing::query()
            ->with('media')
            ->has('media')
            ->limit(10)
            ->get();

        logger()->info('live_e2e.media_sync_results', [
            'listings_with_media' => $listingsWithMedia->count(),
            'total_media_items' => $listingsWithMedia->sum(fn ($l) => $l->media->count()),
        ]);

        // If we have listings with media, verify URLs are valid
        foreach ($listingsWithMedia as $listing) {
            foreach ($listing->media as $media) {
                expect($media->url)->not->toBeNull();
                expect(filter_var($media->url, FILTER_VALIDATE_URL))->not->toBeFalse(
                    "Invalid media URL: {$media->url}"
                );
            }
        }
    });

    it('respects the 30-day modification window', function (): void {
        config()->set('queue.default', 'sync');
        config()->set('media.auto_download', false);

        /** @var IdxClient $client */
        $client = app(IdxClient::class);

        (new ImportPosLast30Days(
            pageSize: 100,
            maxPages: 10,
            days: 30,
            feed: 'idx'
        ))->handle($client);

        $cutoff = now()->subDays(31);

        $listings = Listing::query()
            ->whereNotNull('modified_at')
            ->get();

        foreach ($listings as $listing) {
            expect($listing->modified_at->gte($cutoff))->toBeTrue(
                "Listing {$listing->listing_key} modified_at {$listing->modified_at} is outside 30-day window"
            );
        }
    });

});

describe('API Connectivity Verification', function (): void {

    it('can connect to IDX API', function (): void {
        /** @var IdxClient $client */
        $client = app(IdxClient::class);

        expect($client->isEnabled())->toBeTrue('IDX client should be enabled');

        // Try a minimal API call
        $factory = app(\App\Services\Idx\RequestFactory::class);
        $response = $factory->idxProperty()->get('Property', [
            '$top' => 1,
            '$filter' => "TransactionType eq 'For Sale'",
        ]);

        expect($response->successful())->toBeTrue(
            'IDX API request failed with status: ' . $response->status()
        );
    });

    it('can fetch POS listings from live API', function (): void {
        $factory = app(\App\Services\Idx\RequestFactory::class);

        $filter = ResoFilters::powerOfSale();
        $response = $factory->idxProperty()->get('Property', [
            '$top' => 10,
            '$filter' => $filter,
        ]);

        expect($response->successful())->toBeTrue();

        $items = $response->json('value') ?? [];
        expect(count($items))->toBeGreaterThan(0,
            'Expected to find POS listings via server-side filter'
        );

        // Verify each item has POS keywords
        foreach ($items as $item) {
            $remarks = $item['PublicRemarks'] ?? null;
            expect(ResoFilters::isPowerOfSaleRemarks($remarks))->toBeTrue(
                "Server returned listing without POS keywords: " . ($item['ListingKey'] ?? 'unknown')
            );
        }
    });

});
