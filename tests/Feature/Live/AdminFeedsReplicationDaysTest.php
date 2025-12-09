<?php

declare(strict_types=1);

uses()->group('local-only');

use App\Jobs\BackfillListingMedia;
use App\Jobs\ImportAllPowerOfSaleFeeds;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;

it('runs POS replication with 30 days by default', function (): void {
    Bus::fake();

    Cache::put('admin.feeds.db_stats', [
        'total' => 0,
        'available' => 0,
        'latest' => null,
    ], now()->addMinutes(5));
    Cache::put('admin.feeds.status_counts', [], now()->addMinutes(5));
    Cache::put('admin.feeds.suppression_count', 0, now()->addMinutes(5));
    Cache::put('admin.feeds.price_stats', [
        'avg' => 0.0,
        'min' => 0.0,
        'max' => 0.0,
    ], now()->addMinutes(5));
    Cache::put('admin.feeds.top_municipalities', [], now()->addMinutes(5));

    $admin = User::factory()->admin()->make();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    Volt::test('admin.feeds.index')
        ->call('importBoth');

    Bus::assertChained([
        new ImportAllPowerOfSaleFeeds(pageSize: 100, maxPages: 500, days: 30),
        new BackfillListingMedia,
    ]);
});

it('runs POS replication with custom days from dropdown', function (): void {
    Bus::fake();

    Cache::put('admin.feeds.db_stats', [
        'total' => 0,
        'available' => 0,
        'latest' => null,
    ], now()->addMinutes(5));
    Cache::put('admin.feeds.status_counts', [], now()->addMinutes(5));
    Cache::put('admin.feeds.suppression_count', 0, now()->addMinutes(5));
    Cache::put('admin.feeds.price_stats', [
        'avg' => 0.0,
        'min' => 0.0,
        'max' => 0.0,
    ], now()->addMinutes(5));
    Cache::put('admin.feeds.top_municipalities', [], now()->addMinutes(5));

    $admin = User::factory()->admin()->make();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    $days = 180;

    Volt::test('admin.feeds.index')
        ->set('posReplicationDays', $days)
        ->call('importBoth');

    Bus::assertChained([
        new ImportAllPowerOfSaleFeeds(pageSize: 100, maxPages: 500, days: $days),
        new BackfillListingMedia,
    ]);
});
