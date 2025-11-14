<?php

declare(strict_types=1);

use App\Jobs\BackfillListingMedia;
use App\Jobs\ImportAllPowerOfSaleFeeds;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;

test('stale running progress does not block importBoth when queue is idle', function (): void {
    // Simulate database queue driver to exercise queued-jobs inspection logic
    config()->set('queue.default', 'database');

    // Seed stale running progress (older than 2 minutes)
    Cache::put('idx.import.pos', [
        'status' => 'running',
        'started_at' => now()->subMinutes(10)->toIso8601String(),
        'last_at' => now()->subMinutes(5)->toIso8601String(),
    ], now()->addMinutes(10));

    Bus::fake();

    $admin = \App\Models\User::factory()->admin()->create();
    \Pest\Laravel\actingAs($admin);
    Volt::actingAs($admin);

    // Trigger the action; should ignore stale state and dispatch
    Volt::test('admin.feeds.index')->call('importBoth');

    Bus::assertChained([
        ImportAllPowerOfSaleFeeds::class,
        BackfillListingMedia::class,
    ]);
});
