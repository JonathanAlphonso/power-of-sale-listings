<?php

declare(strict_types=1);

uses()->group('local-only');

use App\Services\Idx\IdxClient;
use Carbon\CarbonImmutable;

it('fetches power of sale listings live', function (): void {
    $idxConfig = config('services.idx');

    if (! filter_var($idxConfig['run_live_tests'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        $this->markTestSkipped('Set RUN_LIVE_IDX_TESTS=1 to enable live IDX smoke tests.');
    }

    /** @var IdxClient $client */
    $client = app(IdxClient::class);

    if (! $client->isEnabled()) {
        $this->markTestSkipped('IDX client is not enabled.');
    }

    $listings = $client->fetchPowerOfSaleListings(4);

    expect($listings)->toBeArray();

    // Non-assertive shape check: if results exist, ensure they look like listings
    if (! empty($listings)) {
        expect($listings[0])->toBeArray();
        expect($listings[0])->toHaveKey('listing_key');
    }
});

it('fetches at least 5000 for-sale public remarks in the last 30 days live', function (): void {
    $idxConfig = config('services.idx');

    if (! filter_var($idxConfig['run_live_tests'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        $this->markTestSkipped('Set RUN_LIVE_IDX_TESTS=1 to enable live IDX smoke tests.');
    }

    $baseUri = rtrim((string) ($idxConfig['base_uri'] ?? ''), '/');
    $token = (string) ($idxConfig['token'] ?? '');

    expect($baseUri)->not->toBe('');
    expect($token)->not->toBe('');

    $now = CarbonImmutable::now('UTC');
    $windowStart = $now->subDays(30);

    /** @var \App\Services\Idx\RequestFactory $factory */
    $factory = app(\App\Services\Idx\RequestFactory::class);
    $request = $factory->idxProperty(preferMaxPage: true);

    $top = 100;
    $cursorTs = $windowStart;
    $cursorKey = '0';
    $remarksTotal = 0;
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

        $response = $request->get('Property', [
            '$filter' => $baseFilter.' and '.$cursorFilter,
            '$orderby' => 'ModificationTimestamp,ListingKey',
            '$top' => $top,
        ]);

        $response->throw();

        $items = $response->json('value') ?? [];
        $items = is_array($items) ? array_values(array_filter($items, 'is_array')) : [];

        foreach ($items as $raw) {
            $remarks = $raw['PublicRemarks'] ?? null;
            if (is_string($remarks) && $remarks !== '') {
                $remarksTotal++;
            }
        }

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
    } while (count($items) === $top && $pages < 300 && $remarksTotal < 5000);

    expect($remarksTotal)->toBeGreaterThanOrEqual(5000);
})
    ->group('live-idx');

it('live 30 day for-sale replication pulls approximately @odata.count records', function (): void {
    $idxConfig = config('services.idx');

    if (! filter_var($idxConfig['run_live_tests'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        $this->markTestSkipped('Set RUN_LIVE_IDX_TESTS=1 to enable live IDX volume tests.');
    }

    $baseUri = rtrim((string) ($idxConfig['base_uri'] ?? ''), '/');
    $token = (string) ($idxConfig['token'] ?? '');

    expect($baseUri)->not->toBe('');
    expect($token)->not->toBe('');

    $now = CarbonImmutable::now('UTC');
    $windowStart = $now->subDays(30);
    $windowIso = $windowStart->toIso8601String();

    /** @var \App\Services\Idx\RequestFactory $factory */
    $factory = app(\App\Services\Idx\RequestFactory::class);
    $request = $factory->idxProperty(preferMaxPage: true)->timeout(180);

    // 1) Count query: how many For Sale records in the last 30 days?
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
        $this->markTestSkipped('IDX reports 0 For Sale records in the last 30 days; volume test not applicable.');
    }

    // 2) Enumerate using the same cursor pattern (ModificationTimestamp + ListingKey)
    $top = 500; // higher page size to handle volume efficiently
    $cursorTs = $windowStart;
    $cursorKey = '0';
    $pulled = 0;
    $pages = 0;

    // Allow enough pages for high volume but cap to avoid runaway loops.
    $maxPages = min(1000, (int) ceil($expectedCount / $top) + 20);

    do {
        $pages++;

        $ts = $cursorTs->toIso8601String();
        $cursorFilter = sprintf(
            "ModificationTimestamp gt %s or (ModificationTimestamp eq %s and ListingKey gt '%s')",
            $ts,
            $ts,
            str_replace("'", "''", $cursorKey === '' ? '0' : $cursorKey),
        );

        $response = $request->get('Property', [
            '$filter' => "TransactionType eq 'For Sale' and ".$cursorFilter,
            '$orderby' => 'ModificationTimestamp,ListingKey',
            '$top' => $top,
        ]);

        $response->throw();

        $items = $response->json('value') ?? [];
        $items = is_array($items) ? array_values(array_filter($items, 'is_array')) : [];

        $pulled += count($items);

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
    } while (count($items) === $top && $pages < $maxPages && $pulled < $expectedCount);

    // 3) The enumerated total must meet or exceed the reported @odata.count.
    // With timestamp + key cursor replication following the official example,
    // we should not miss records; new records created during enumeration only
    // increase the pulled total.
    expect($pulled)->toBeGreaterThanOrEqual($expectedCount);
})
    ->group('live-idx');
