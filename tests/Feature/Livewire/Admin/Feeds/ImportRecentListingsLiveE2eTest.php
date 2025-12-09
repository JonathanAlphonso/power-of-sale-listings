<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\Source;
use Carbon\CarbonImmutable;

test('live 24h import button matches IDX API count', function (): void {
    $idxConfig = config('services.idx');

    if (! filter_var($idxConfig['run_live_tests'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        $this->markTestSkipped('Set RUN_LIVE_IDX_TESTS=1 to enable live 24h import E2E test.');
    }

    $baseUri = rtrim((string) ($idxConfig['base_uri'] ?? ''), '/');
    $token = (string) ($idxConfig['token'] ?? '');

    expect($baseUri)->not->toBe('');
    expect($token)->not->toBe('');

    config()->set('queue.default', 'sync');

    // Freeze time so the API enumeration and job use the same 24h window
    $now = CarbonImmutable::now('UTC');
    CarbonImmutable::setTestNow($now);
    $windowStart = $now->subDay();

    // 1) Enumerate live IDX records for the last 24h using the same cursor
    // pattern as the job (ModificationTimestamp + ListingKey). This is our expected count.
    $top = 50;
    $cursorTs = $windowStart;
    $cursorKey = '0';
    $expectedCount = 0;
    $pages = 0;

    do {
        $pages++;

        $ts = $cursorTs->toIso8601String();
        $baseFilter = "TransactionType eq 'For Sale'";
        $cursorFilter = sprintf(
            "ModificationTimestamp gt %s or (ModificationTimestamp eq %s and ListingKey gt '%s')",
            $ts,
            $ts,
            str_replace("'", "''", $cursorKey === '' ? '0' : $cursorKey),
        );

        /** @var \App\Services\Idx\RequestFactory $factory */
        $factory = app(\App\Services\Idx\RequestFactory::class);
        $request = $factory->idxProperty(preferMaxPage: true);

        $response = $request
            ->get('Property', [
                '$filter' => $baseFilter.' and '.$cursorFilter,
                '$orderby' => 'ModificationTimestamp,ListingKey',
                '$top' => $top,
            ]);

        $response->throw();

        $items = $response->json('value') ?? [];
        $items = is_array($items) ? array_values(array_filter($items, 'is_array')) : [];

        $expectedCount += count($items);

        if ($items !== []) {
            $last = end($items);
            $tsStr = $last['ModificationTimestamp'] ?? $last['OriginalEntryTimestamp'] ?? null;
            $key = $last['ListingKey'] ?? null;

            if (is_string($tsStr) && $tsStr !== '' && is_string($key) && $key !== '') {
                $candidate = CarbonImmutable::parse($tsStr)->utc();
                if ($candidate->lt($cursorTs)) {
                    $candidate = $cursorTs;
                }
                $cursorTs = $candidate;
                $cursorKey = $key;
            }
        }
    } while (count($items) === $top && $pages < 200);

    // 2) Start from a clean slate for listings in the test DB
    Listing::query()->delete();

    // 3) Trigger the actual Livewire button, with sync queue, so the job
    // runs inline in this process and uses the same frozen time window.
    $admin = \App\Models\User::factory()->admin()->create();
    $this->actingAs($admin);
    \Livewire\Volt\Volt::actingAs($admin);

    \Livewire\Volt\Volt::test('admin.feeds.index')
        ->call('importRecentListings')
        ->assertSet('notice', __('Recent (24h) import queued'));

    // 4) Count imported IDX listings only (source slug = 'idx')
    $idxSourceId = Source::query()->where('slug', 'idx')->value('id');
    expect($idxSourceId)->not->toBeNull();

    $importedCount = Listing::query()
        ->where('source_id', $idxSourceId)
        ->where('transaction_type', 'For Sale')
        ->whereNotNull('external_id')
        ->count();

    // Allow a small +/- 1 drift to account for records being
    // created or modified between the enumeration and the job run.
    expect($importedCount)->toBeGreaterThanOrEqual($expectedCount - 1);
    expect($importedCount)->toBeLessThanOrEqual($expectedCount + 1);
})->group('local-only');
