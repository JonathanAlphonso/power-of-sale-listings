<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

it('shows queued notice prominently and with at least 10s visibility', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($user)
        ->get('/admin/feeds?notice='.urlencode('VOW import queued'));

    $response->assertOk();

    $html = (string) $response->getContent();

    // Assert the callout includes an Alpine timer for at least 10 seconds (we set 15000ms)
    expect($html)->toContain('x-init="setTimeout(() => visible = false, 15000)');
    expect($html)->toContain('data-min-visible-ms="15000"');

    // Assert the notice appears before the main status section so users see it immediately
    $noticePos = strpos($html, 'data-min-visible-ms="15000"');
    $idxPos = strpos($html, 'IDX / PropTx Status');
    expect($noticePos)->not->toBeFalse();
    expect($idxPos)->not->toBeFalse();
    expect($noticePos < $idxPos)->toBeTrue();
});
