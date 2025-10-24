<?php

use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use Livewire\Volt\Volt;

test('guests are redirected from the listings index', function (): void {
    $this->get(route('admin.listings.index'))->assertRedirect(route('login'));
});

test('authenticated users can browse, filter, and preview listings', function (): void {
    $user = User::factory()->create();

    $toronto = Municipality::factory()->create(['name' => 'Toronto']);
    $ottawa = Municipality::factory()->create(['name' => 'Ottawa']);

    $rentListing = Listing::factory()
        ->for($toronto)
        ->create([
            'mls_number' => 'C1234567',
            'street_address' => '101 Rental Lane',
            'display_status' => 'Available',
            'sale_type' => 'RENT',
            'list_price' => 2400,
            'modified_at' => now()->subDays(2),
        ]);

    $saleListing = Listing::factory()
        ->for($ottawa)
        ->create([
            'mls_number' => 'K7654321',
            'street_address' => '202 Market Street',
            'display_status' => 'Conditionally Sold',
            'sale_type' => 'SALE',
            'list_price' => 650000,
            'modified_at' => now()->subDay(),
        ]);

    $this->actingAs($user);

    Volt::test('admin.listings.index')
        ->assertSee($rentListing->mls_number)
        ->assertSee($saleListing->mls_number)
        ->set('search', $saleListing->mls_number)
        ->assertSee($saleListing->street_address)
        ->assertDontSee($rentListing->street_address)
        ->set('search', '')
        ->set('municipalityId', (string) $toronto->id)
        ->assertSee($rentListing->street_address)
        ->assertDontSee($saleListing->street_address)
        ->set('municipalityId', '')
        ->set('saleType', 'SALE')
        ->assertSee($saleListing->street_address)
        ->assertDontSee($rentListing->street_address)
        ->call('selectListing', $saleListing->id)
        ->assertSet('selectedListingId', $saleListing->id)
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('saleType', '')
        ->assertSet('status', '')
        ->assertSet('municipalityId', '')
        ->assertSee($rentListing->street_address)
        ->assertSee($saleListing->street_address);
});
