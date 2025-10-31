<?php

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows admins to administer listings', function (): void {
    $admin = User::factory()->admin()->create();
    $listing = Listing::factory()->create();

    expect(Gate::forUser($admin)->allows('viewAny', Listing::class))->toBeTrue();
    expect($admin->can('view', $listing))->toBeTrue();
    expect($admin->can('update', $listing))->toBeTrue();
    expect($admin->can('suppress', $listing))->toBeTrue();
    expect($admin->can('unsuppress', $listing))->toBeTrue();
});

it('prevents subscribers from administering listings', function (): void {
    $subscriber = User::factory()->create();
    $listing = Listing::factory()->create();

    expect(Gate::forUser($subscriber)->denies('viewAny', Listing::class))->toBeTrue();
    expect($subscriber->can('view', $listing))->toBeFalse();
    expect($subscriber->can('suppress', $listing))->toBeFalse();
});

it('denies suspended admins from administering listings', function (): void {
    $suspendedAdmin = User::factory()->admin()->suspended()->create();
    $listing = Listing::factory()->create();

    expect(Gate::forUser($suspendedAdmin)->denies('viewAny', Listing::class))->toBeTrue();
    expect($suspendedAdmin->can('update', $listing))->toBeFalse();
    expect($suspendedAdmin->can('unsuppress', $listing))->toBeFalse();
});
