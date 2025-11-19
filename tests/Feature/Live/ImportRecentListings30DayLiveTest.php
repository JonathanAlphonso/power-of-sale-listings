<?php

declare(strict_types=1);

use App\Jobs\ImportRecentListings;
use App\Models\Listing;
use App\Models\Source;
use App\Services\Idx\IdxClient;
use App\Services\Idx\RequestFactory;
use Carbon\CarbonImmutable;

it('imports 30 day for-sale listings in line with live IDX count', function (): void {
    $idxConfig = config('services.idx');

    if (! filter_var($idxConfig['run_live_tests'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        $this->markTestSkipped('Set RUN_LIVE_IDX_TESTS=1 to enable live 30 day import test.');
    }

    /** @var IdxClient $client */
    $client = app(IdxClient::class);
    if (! $client->isEnabled()) {
        $this->markTestSkipped('IDX client is not enabled. Set IDX_BASE_URI and IDX_TOKEN.');
    }

    $now = CarbonImmutable::now('UTC');
    $windowStart = $now->subDays(30);
    $windowIso = $windowStart->toIso8601String();

    /** @var RequestFactory $factory */
    $factory = app(RequestFactory::class);
    $request = $factory->idxProperty(preferMaxPage: true)->timeout(180);

    // 1) Live count query for last 30 days For Sale
    $countResponse = $request->get('Property', [
        '$filter' => sprintf("TransactionType eq 'For Sale' and ModificationTimestamp ge %s", $windowIso),
        '$orderby' => 'ModificationTimestamp,ListingKey',
        '$top' => 0,
        '$count' => 'true',
    ]);

    $countResponse->throw();

    $root = $countResponse->json();
    $rawCount = $root['@odata.count'] ?? null;

    if (! is_int($rawCount) && ! is_numeric($rawCount)) {
        $this->markTestSkipped('IDX did not return a numeric @odata.count for the 30 day window.');
    }

    $expectedCount = (int) $rawCount;

    if ($expectedCount === 0) {
        $this->markTestSkipped('IDX reports 0 For Sale records in the last 30 days; import volume test not applicable.');
    }

    // 2) Start from a clean slate for listings in the test DB
    Listing::query()->delete();

    // 3) Run the ImportRecentListings job inline (no queue, no Livewire)
    $job = new ImportRecentListings(pageSize: 100, maxPages: 1000, windowStartIso: $windowIso);
    $job->handle($client);

    // 4) Count imported IDX listings matching the same 30 day window
    $idxSourceId = Source::query()->where('slug', 'idx')->value('id');
    expect($idxSourceId)->not->toBeNull();

    $importedCount = Listing::query()
        ->where('source_id', $idxSourceId)
        ->where('transaction_type', 'For Sale')
        ->whereNotNull('external_id')
        ->where('modified_at', '>=', $windowStart)
        ->count();

    expect($importedCount)->toBeGreaterThanOrEqual($expectedCount);
})->group('live-idx');
