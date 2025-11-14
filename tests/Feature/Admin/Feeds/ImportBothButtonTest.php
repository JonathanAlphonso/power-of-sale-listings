<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;

test('import both button prevents duplicate dispatches with cache flag', function () {
    // Use database queue to actually queue jobs
    config(['queue.default' => 'database']);

    $admin = User::factory()->create(['role' => 'admin']);

    // Clean up any existing jobs and cache
    if (DB::getDriverName() !== 'sqlite' || DB::getDatabaseName() !== ':memory:') {
        DB::table('jobs')->delete();
    }
    Cache::forget('idx.import.pos');
    Cache::forget('idx.import.pos.dispatching');

    // Fake Bus to prevent actual job execution
    Bus::fake();

    // Create a component instance and call importBoth
    $component = Volt::actingAs($admin)->test('admin.feeds.index');
    $component->call('importBoth');

    // Verify the cache flag was set
    expect(Cache::has('idx.import.pos.dispatching'))->toBeTrue(
        'Cache flag should be set after dispatch to prevent race condition'
    );

    // Attempt second call on same component instance (simulating rapid clicks)
    $component->call('importBoth');
    $component->assertSet('notice', __('Import is being queued, please wait...'));
});

test('import both button blocks when jobs already queued in database', function () {
    // Use database queue
    config(['queue.default' => 'database']);

    $admin = User::factory()->create(['role' => 'admin']);

    // Clean up
    if (DB::getDriverName() !== 'sqlite' || DB::getDatabaseName() !== ':memory:') {
        DB::table('jobs')->delete();
    }
    Cache::forget('idx.import.pos');
    Cache::forget('idx.import.pos.dispatching');

    Bus::fake();

    // First dispatch
    Volt::actingAs($admin)
        ->test('admin.feeds.index')
        ->call('importBoth')
        ->assertSet('notice', __('Import queued'));

    // Clear the dispatching flag to simulate time passing
    Cache::forget('idx.import.pos.dispatching');

    // Now manually insert a job to simulate it being queued
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\Jobs\ImportAllPowerOfSaleFeeds']),
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    // Second dispatch should be blocked
    Volt::actingAs($admin)
        ->test('admin.feeds.index')
        ->call('importBoth')
        ->assertSet('notice', __('Import already queued'));
});

test('import both button clears stale progress and allows redispatch', function () {
    config(['queue.default' => 'database']);

    $admin = User::factory()->create(['role' => 'admin']);

    // Set stale progress (last activity > 2 minutes ago)
    Cache::put('idx.import.pos', [
        'status' => 'running',
        'started_at' => now()->subMinutes(10)->toIso8601String(),
        'last_at' => now()->subMinutes(5)->toIso8601String(),
    ]);

    if (DB::getDriverName() !== 'sqlite' || DB::getDatabaseName() !== ':memory:') {
        DB::table('jobs')->delete();
    }

    Bus::fake();

    // Should clear stale cache and allow dispatch
    Volt::actingAs($admin)
        ->test('admin.feeds.index')
        ->call('importBoth')
        ->assertSet('notice', __('Import queued'));

    // Verify cache flag is set
    expect(Cache::has('idx.import.pos.dispatching'))->toBeTrue();
});
