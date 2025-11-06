<?php

declare(strict_types=1);

use App\Services\Idx\IdxClient;

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
