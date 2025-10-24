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

    Listing::factory()->create([
        'display_status' => 'Available',
        'sale_type' => 'RENT',
        'list_price' => 450000,
    ]);

    $response = $this->get(route('dashboard'));
    $response
        ->assertStatus(200)
        ->assertSee('Total listings')
        ->assertSee('Open listings workspace')
        ->assertSee('Available inventory')
        ->assertSee(route('admin.listings.index'), false);
});
