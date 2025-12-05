<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

test('non-admin users are not auto-promoted when admins exist', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $user = User::factory()->create([
        'role' => UserRole::Subscriber,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('admin.listings.index'));

    $response->assertForbidden();

    $user->refresh();
    expect($user->role)->toBe(UserRole::Subscriber);
    $admin->refresh();
    expect($admin->role)->toBe(UserRole::Admin);
});

test('non-admin users are not auto-promoted in non-local environments even when no admins exist', function (): void {
    config()->set('app.env', 'production');
    config()->set('app.debug', false);

    User::query()->where('role', UserRole::Admin)->delete();

    $user = User::factory()->create([
        'role' => UserRole::Subscriber,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('admin.listings.index'));

    $response->assertForbidden();

    $user->refresh();
    expect($user->role)->toBe(UserRole::Subscriber);
});

test('emergency promotion is allowed in local debug environments when no admins exist', function (): void {
    config()->set('app.env', 'local');
    config()->set('app.debug', true);

    User::query()->where('role', UserRole::Admin)->delete();

    $user = User::factory()->create([
        'role' => UserRole::Subscriber,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('admin.listings.index'));

    $response->assertOk();

    $user->refresh();
    expect($user->role)->toBe(UserRole::Admin);
});
