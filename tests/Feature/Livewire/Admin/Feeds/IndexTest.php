<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Idx\IdxClient;
use Livewire\Volt\Volt;

test('guests are redirected from the data feeds page', function (): void {
    $this->get(route('admin.feeds.index'))->assertRedirect(route('login'));
});

test('subscribers cannot access the data feeds page', function (): void {
    // Ensure an admin exists so a subscriber is not auto-promoted.
    User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get(route('admin.feeds.index'))->assertForbidden();
});

test('admins can view the data feeds page', function (): void {
    $admin = User::factory()->admin()->create();

    // Avoid live calls; ensure page renders with mocked service.
    $mock = \Mockery::mock(IdxClient::class);
    $mock->shouldReceive('isEnabled')->andReturn(true);
    $mock->shouldReceive('fetchPowerOfSaleListings')->andReturn([]);
    $this->instance(IdxClient::class, $mock);

    $this->actingAs($admin);
    Volt::actingAs($admin);

    $response = $this->get(route('admin.feeds.index'));
    $response->assertOk();
    $response->assertSeeText('Data Feeds');
    $response->assertSeeText('IDX / PropTx Status');
});
