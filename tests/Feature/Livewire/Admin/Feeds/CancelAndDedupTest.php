<?php

declare(strict_types=1);

use App\Jobs\ImportAllPowerOfSaleFeeds;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;

test('cancel queued imports removes matching jobs and shows a notice', function (): void {
    config()->set('queue.default', 'database');
    $table = config('queue.connections.database.table', 'jobs');

    // Seed two fake queued jobs containing our class names
    DB::table($table)->insert([
        [
            'queue' => 'default',
            'payload' => json_encode(['displayName' => ImportAllPowerOfSaleFeeds::class]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time(),
            'created_at' => time(),
        ],
        [
            'queue' => 'default',
            'payload' => '...'.ImportAllPowerOfSaleFeeds::class.'...',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time(),
            'created_at' => time(),
        ],
    ]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    expect(DB::table($table)->count())->toBeGreaterThan(0);

    Volt::test('admin.feeds.index')->call('cancelQueuedImports');
    expect(DB::table($table)->count())->toBe(0);
});

test('import both does not queue when running or already queued', function (): void {
    config()->set('queue.default', 'database');
    $table = config('queue.connections.database.table', 'jobs');

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    // Case 1: running
    Cache::put('idx.import.pos', ['status' => 'running'], now()->addMinute());
    Bus::fake();
    Volt::test('admin.feeds.index')->call('importBoth');
    Bus::assertNotDispatched(ImportAllPowerOfSaleFeeds::class);

    // Case 2: queued
    Cache::forget('idx.import.pos');
    DB::table($table)->insert([
        [
            'queue' => 'default',
            'payload' => '...'.ImportAllPowerOfSaleFeeds::class.'...',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time(),
            'created_at' => time(),
        ],
    ]);

    Bus::fake();
    $before = DB::table($table)->count();
    Volt::test('admin.feeds.index')->call('importBoth');
    Bus::assertNotDispatched(ImportAllPowerOfSaleFeeds::class);
    expect(DB::table($table)->count())->toBe($before);
});
