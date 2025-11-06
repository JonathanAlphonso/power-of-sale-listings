<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

it('requires authentication', function (): void {
    $this->get('/admin/feeds')->assertRedirect();
});

it('renders admin feeds page', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($user);

    $this->get('/admin/feeds')
        ->assertOk()
        ->assertSee('Data Feeds')
        ->assertSee('IDX / PropTx Status')
        ->assertSee('VOW / PropTx Status')
        ->assertSee('Test IDX (30)')
        ->assertSee('Test VOW (30)');
});
