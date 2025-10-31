<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows admins to manage user records', function (): void {
    $admin = User::factory()->admin()->create();
    $teammate = User::factory()->create();

    expect(Gate::forUser($admin)->allows('viewAny', User::class))->toBeTrue();
    expect($admin->can('view', $teammate))->toBeTrue();
    expect($admin->can('create', User::class))->toBeTrue();
    expect($admin->can('update', $teammate))->toBeTrue();
    expect($admin->can('delete', $teammate))->toBeTrue();
    expect($admin->can('suspend', $teammate))->toBeTrue();
    expect($admin->can('forcePasswordRotation', $teammate))->toBeTrue();
    expect($admin->can('sendPasswordResetLink', $teammate))->toBeTrue();
});

it('prevents subscribers from administering user records', function (): void {
    $subscriber = User::factory()->create();
    $teammate = User::factory()->create();

    expect(Gate::forUser($subscriber)->denies('viewAny', User::class))->toBeTrue();
    expect($subscriber->can('view', $subscriber))->toBeTrue();
    expect($subscriber->can('update', $subscriber))->toBeTrue();
    expect($subscriber->can('create', User::class))->toBeFalse();
    expect($subscriber->can('update', $teammate))->toBeFalse();
    expect($subscriber->can('delete', $teammate))->toBeFalse();
    expect($subscriber->can('suspend', $teammate))->toBeFalse();
});

it('prevents administrators from deleting or suspending themselves', function (): void {
    $admin = User::factory()->admin()->create();

    expect($admin->can('delete', $admin))->toBeFalse();
    expect($admin->can('suspend', $admin))->toBeFalse();
});

it('denies suspended admins from managing user records', function (): void {
    $suspendedAdmin = User::factory()->admin()->suspended()->create();
    $teammate = User::factory()->create();

    expect(Gate::forUser($suspendedAdmin)->denies('viewAny', User::class))->toBeTrue();
    expect($suspendedAdmin->can('update', $teammate))->toBeFalse();
    expect($suspendedAdmin->can('delete', $teammate))->toBeFalse();
});
