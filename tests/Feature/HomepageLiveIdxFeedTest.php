<?php

declare(strict_types=1);

use App\Services\Idx\IdxClient;

it('renders the live idx feed on the homepage', function (): void {
    $idxConfig = config('services.idx');

    if (! filter_var($idxConfig['run_live_tests'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        $this->markTestSkipped('Set RUN_LIVE_IDX_TESTS=1 to enable live homepage IDX test.');
    }

    /** @var IdxClient $client */
    $client = app(IdxClient::class);

    if (! $client->isEnabled()) {
        $this->markTestSkipped('IDX client is not enabled.');
    }

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSeeText('Live IDX feed');
    $response->assertDontSeeText('IDX credentials required');

    $content = (string) $response->getContent();
    $statusOk = str_contains($content, 'Connected to IDX')
        || str_contains($content, 'No live listings returned');

    expect($statusOk)->toBeTrue();
});
