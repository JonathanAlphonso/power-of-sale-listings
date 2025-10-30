<?php

use App\Models\Listing;
use App\Models\User;

test('guests are redirected from the dashboard', function (): void {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('subscribers cannot access the dashboard when an admin exists', function (): void {
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->create();

    $this->actingAs($subscriber)
        ->get(route('dashboard'))
        ->assertForbidden();

    expect($admin->refresh()->isAdmin())->toBeTrue();
    expect($subscriber->refresh()->isAdmin())->toBeFalse();
});

test('admins can view the dashboard', function (): void {
    $admin = User::factory()->admin()->create();

    $availableListing = Listing::factory()->create([
        'display_status' => 'Available',
        'street_address' => '77 Market Street',
        'list_price' => 450000,
        'modified_at' => now(),
    ]);

    Listing::factory()
        ->create([
            'display_status' => 'Sold',
            'street_address' => '88 Bay Street',
            'modified_at' => now()->subDay(),
        ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Total listings')
        ->assertSee('Open listings workspace')
        ->assertSee('Available inventory')
        ->assertSee('Team members')
        ->assertSee('Manage users')
        ->assertSee($availableListing->street_address)
        ->assertSee(route('admin.listings.index'), false);
});
