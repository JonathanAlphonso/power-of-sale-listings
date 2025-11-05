<?php

declare(strict_types=1);

use App\Services\Idx\IdxClient;
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

it('fetches media data (images) from the idx api', function (): void {
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
        ->get('Media', [
            '$top' => 4,
        ]);

    $response->throw();

    expect($response->status())->toBe(200);
    expect($response->json('value'))->toBeArray();
    expect($response->json('value'))->not->toBeEmpty();
    expect($response->json('value.0'))->toBeArray();

    // Additionally confirm at least one item has an accessible image URL.
    $items = $response->json('value');
    $imageItem = collect($items)
        ->first(function ($item) {
            return is_array($item)
                && ! empty($item['MediaURL'] ?? null)
                && is_string($item['MediaURL'])
                && str_starts_with((string) ($item['MediaType'] ?? ''), 'image/');
        });

    expect($imageItem)->not->toBeNull();

    $imageUrl = $imageItem['MediaURL'];

    // Try HEAD first to avoid downloading the entire image
    $head = Http::retry(2, 200)
        ->timeout(10)
        ->accept('*/*')
        ->head($imageUrl);

    // If HEAD is not allowed, fall back to a ranged GET fetching 1 byte
    if ($head->status() === 405 || $head->status() === 400) {
        $head = Http::retry(2, 200)
            ->timeout(10)
            ->withHeaders(['Range' => 'bytes=0-0'])
            ->get($imageUrl);
    }

    expect($head->successful())->toBeTrue();
    $contentType = $head->header('content-type');
    expect($contentType)->toBeString();
    expect(str_starts_with(strtolower($contentType), 'image/'))->toBeTrue();
});

it('service attaches image_url to demo listings', function (): void {
    $idxConfig = config('services.idx');

    if (! filter_var($idxConfig['run_live_tests'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        $this->markTestSkipped('Set RUN_LIVE_IDX_TESTS=1 to enable live IDX smoke tests.');
    }

    /** @var IdxClient $client */
    $client = app(IdxClient::class);

    if (! $client->isEnabled()) {
        $this->markTestSkipped('IDX client is not enabled.');
    }

    $listings = $client->fetchListings(4);

    expect($listings)->toBeArray();
    expect($listings)->not->toBeEmpty();

    $withImage = collect($listings)->first(fn ($l) => is_array($l) && ! empty($l['image_url'] ?? null));
    expect($withImage)->not->toBeNull();

    $url = $withImage['image_url'];

    $head = Http::retry(2, 200)
        ->timeout(10)
        ->accept('*/*')
        ->head($url);

    if ($head->status() === 405 || $head->status() === 400) {
        $head = Http::retry(2, 200)
            ->timeout(10)
            ->withHeaders(['Range' => 'bytes=0-0'])
            ->get($url);
    }

    expect($head->successful())->toBeTrue();
    $contentType = $head->header('content-type');
    expect($contentType)->toBeString();
    expect(str_starts_with(strtolower($contentType), 'image/'))->toBeTrue();
});
