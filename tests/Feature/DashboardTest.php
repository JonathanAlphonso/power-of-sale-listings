<?php

use App\Models\Listing;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $saleListing = Listing::factory()->create([
        'display_status' => 'Available',
        'sale_type' => 'SALE',
        'street_address' => '77 Market Street',
        'list_price' => 450000,
        'modified_at' => now(),
    ]);

    Listing::factory()
        ->rent()
        ->create([
            'display_status' => 'Available',
            'street_address' => '88 Rental Road',
            'modified_at' => now()->subDay(),
        ]);

    $response = $this->get(route('dashboard'));
    $response
        ->assertStatus(200)
        ->assertSee('Total listings')
        ->assertSee('Open listings workspace')
        ->assertSee('Available inventory')
        ->assertSee('Team members')
        ->assertSee('Manage users')
        ->assertSee($saleListing->street_address)
        ->assertDontSee('Rental opportunities')
        ->assertDontSee('88 Rental Road')
        ->assertSee(route('admin.listings.index'), false);
});
