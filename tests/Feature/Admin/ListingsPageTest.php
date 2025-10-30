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

    $availableListing = Listing::factory()
        ->for($toronto)
        ->create([
            'mls_number' => 'C1234567',
            'street_address' => '101 Market Lane',
            'display_status' => 'Available',
            'list_price' => 540000,
            'modified_at' => now()->subDays(2),
        ]);

    $conditionalListing = Listing::factory()
        ->for($ottawa)
        ->create([
            'mls_number' => 'K7654321',
            'street_address' => '202 Market Street',
            'display_status' => 'Conditionally Sold',
            'list_price' => 650000,
            'modified_at' => now()->subDay(),
        ]);

    $this->actingAs($user);

    Volt::test('admin.listings.index')
        ->assertSee($availableListing->mls_number)
        ->assertSee($conditionalListing->mls_number)
        ->set('search', $conditionalListing->mls_number)
        ->assertSee($conditionalListing->street_address)
        ->assertDontSee($availableListing->street_address)
        ->set('search', '')
        ->set('municipalityId', (string) $toronto->id)
        ->assertSee($availableListing->street_address)
        ->assertDontSee($conditionalListing->street_address)
        ->set('status', 'Conditionally Sold')
        ->assertSee('No listings match the current filters.')
        ->set('status', '')
        ->set('municipalityId', '')
        ->set('status', 'Conditionally Sold')
        ->assertSee($conditionalListing->street_address)
        ->assertDontSee($availableListing->street_address)
        ->call('selectListing', $conditionalListing->id)
        ->assertSet('selectedListingId', $conditionalListing->id)
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('status', '')
        ->assertSet('municipalityId', '')
        ->assertSee($availableListing->street_address)
        ->assertSee($conditionalListing->street_address);
});
