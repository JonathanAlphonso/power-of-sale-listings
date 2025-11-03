<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

it('fetches property data from the idx api', function (): void {
    $idxConfig = config('services.idx');

    if (! filter_var($idxConfig['run_live_tests'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        $this->markTestSkipped('Set RUN_LIVE_IDX_TESTS=1 to enable live IDX smoke tests.');
    }

    $baseUri = rtrim((string) ($idxConfig['base_uri'] ?? ''), '/');
    $token = $idxConfig['token'] ?? null;

    expect($baseUri)->not->toBe('');
    expect($token)->not->toBeNull();
    expect($token)->not->toBe('');

    $response = Http::retry(3, 250)
        ->timeout(10)
        ->baseUrl($baseUri)
        ->withToken($token)
        ->acceptJson()
        ->get('Property', [
            '$top' => 4,
        ]);

    $response->throw();

    expect($response->status())->toBe(200);
    expect($response->json('value'))->toBeArray();
    expect($response->json('value'))->not->toBeEmpty();
    expect($response->json('value.0'))->toBeArray();
});
