<?php

declare(strict_types=1);

uses()->group('local-only');

use App\Models\Listing;
use App\Services\Idx\IdxClient;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;

it('runs a full live import and surfaces listings + media (URLs only)', function (): void {
    // Guard: only run when explicitly enabled
    $enabled = (bool) filter_var(config('services.idx.run_long_live_tests', false), FILTER_VALIDATE_BOOLEAN);
    if (! $enabled) {
        $this->markTestSkipped('Set RUN_LONG_LIVE_IDX_TESTS=1 to enable full live import test.');
    }

    /** @var IdxClient $client */
    $client = app(IdxClient::class);
    if (! $client->isEnabled()) {
        $this->markTestSkipped('IDX client not enabled. Set IDX_BASE_URI and IDX_TOKEN.');
    }

    // Ensure queued jobs run inline to allow this test to complete
    config()->set('queue.default', 'sync');
    // Do not download images; we only want to persist remote URLs
    config()->set('media.auto_download', false);

    // Clear previous progress markers to avoid dedupe short-circuit
    Cache::forget('idx.import.pos');

    // Act as admin and trigger the full import + media backfill via the Volt page action
    $admin = \App\Models\User::factory()->admin()->create();
    $this->actingAs($admin);
    Volt::actingAs($admin);

    Volt::test('admin.feeds.index')->call('importBoth');

    // Verify import completion (best-effort via progress cache) and presence of data
    $status = (array) Cache::get('idx.import.pos', []);
    expect(($status['status'] ?? null))->toBe('completed');
    expect((int) ($status['items_total'] ?? 0))->toBeGreaterThan(0);

    // Listings should now exist and have media rows (URLs only)
    $some = Listing::query()->with('media')->latest('id')->limit(5)->get();
    expect($some->count())->toBeGreaterThan(0);
    $withMedia = $some->filter(fn ($l) => $l->media->count() > 0)->values();
    expect($withMedia->count())->toBeGreaterThan(0);

    // None of the media should be downloaded (since auto_download=false)
    foreach ($withMedia as $listing) {
        $m = $listing->media->first();
        expect((string) ($m->url ?? ''))->not->toBe('');
        expect($m->stored_path)->toBeNull();
    }

    // Public index should be reachable and contain cards
    $index = $this->get(route('listings.index'));
    $index->assertOk();

    // Choose up to 5 listings with media and assert show pages render
    foreach ($withMedia->take(5) as $listing) {
        $this->get($listing->url)->assertOk();
    }
});
