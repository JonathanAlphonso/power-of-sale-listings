<?php

use App\Models\Listing;
use App\Models\ListingStatusHistory;

test('guests can browse current listings', function (): void {
    $torontoListing = Listing::factory()->create([
        'street_address' => '101 Harbour Street',
        'city' => 'Toronto',
        'display_status' => 'Available',
        'list_price' => 625000,
        'modified_at' => now(),
    ]);

    Listing::factory()->create([
        'street_address' => '202 Wellington Street',
        'display_status' => 'Sold',
        'modified_at' => now()->subDay(),
    ]);

    $this->get(route('listings.index'))
        ->assertOk()
        ->assertSee('Current listings')
        ->assertSee($torontoListing->street_address)
        ->assertSee('$625,000')
        ->assertSee('202 Wellington Street')
        ->assertSee(route('listings.show', $torontoListing), false);
});

test('listings pagination links are rendered', function (): void {
    // Create 25 listings to ensure pagination appears (default 12 per page)
    Listing::factory()->count(25)->create([
        'display_status' => 'Available',
        'modified_at' => now(),
    ]);

    // Livewire 3 pagination uses gotoPage wire:click handlers
    // Check that pagination navigation exists with "Next" link to page 2
    Livewire\Volt\Volt::test('listings.index')
        ->assertSeeHtml('gotoPage(2');
});

test('guests can view a listing detail page', function (): void {
    $listing = Listing::factory()->create([
        'street_address' => '34 Lampman Lane',
        'city' => 'Barrie',
        'neighbourhood' => 'Letitia Heights',
        'province' => 'Ontario',
        'postal_code' => 'L4N5B1',
        'display_status' => 'Available',
        'mls_number' => 'S12426397',
        'list_price' => 675000,
        'original_list_price' => 650000,
        'bedrooms' => 3,
        'bathrooms' => 2,
        'square_feet' => 1400,
        'modified_at' => now(),
    ]);

    $this->get(route('listings.show', $listing))
        ->assertOk()
        ->assertSee('$675,000')
        ->assertSee('34 LAMPMAN LANE')
        ->assertSee('Barrie (Letitia Heights), Ontario L4N5B1')
        ->assertSee('MLS® Number: S12426397');
});

test('listings statistics are displayed for filtered results', function (): void {
    Listing::factory()->create([
        'display_status' => 'Available',
        'list_price' => 400000,
        'days_on_market' => 10,
    ]);

    Listing::factory()->create([
        'display_status' => 'Available',
        'list_price' => 600000,
        'days_on_market' => 20,
    ]);

    Listing::factory()->create([
        'display_status' => 'Available',
        'list_price' => 800000,
        'days_on_market' => 30,
    ]);

    Livewire\Volt\Volt::test('listings.index')
        ->assertSee('Price range')
        ->assertSee('Median price')
        ->assertSee('Avg. days on market')
        ->assertSee('Total listings')
        ->assertSee('$600,000') // Median price
        ->assertSee('20 days'); // Average days on market
});

test('price change indicator shows when price differs from original', function (): void {
    // Price reduced listing
    $reducedListing = Listing::factory()->create([
        'street_address' => '100 Reduced Lane',
        'display_status' => 'Available',
        'list_price' => 450000,
        'original_list_price' => 500000,
    ]);

    // Price increased listing
    $increasedListing = Listing::factory()->create([
        'street_address' => '200 Increased Ave',
        'display_status' => 'Available',
        'list_price' => 550000,
        'original_list_price' => 500000,
    ]);

    // No change listing
    $unchangedListing = Listing::factory()->create([
        'street_address' => '300 Same Price Blvd',
        'display_status' => 'Available',
        'list_price' => 500000,
        'original_list_price' => 500000,
    ]);

    $component = Livewire\Volt\Volt::test('listings.index');

    // Price reduced should show green indicator with down arrow (10% reduction)
    $component->assertSeeHtml('bg-green-100');

    // Price increased should show red indicator with up arrow (10% increase)
    $component->assertSeeHtml('bg-red-100');
});

test('suppressed listings are hidden from the public catalog', function (): void {
    $visibleListing = Listing::factory()->create([
        'street_address' => '101 Harbour Street',
        'city' => 'Toronto',
        'display_status' => 'Available',
        'modified_at' => now(),
    ]);

    $suppressedListing = Listing::factory()
        ->suppressed()
        ->create([
            'street_address' => 'Hidden Lane',
            'display_status' => 'Available',
            'modified_at' => now()->subDay(),
        ]);

    $expiredSuppression = Listing::factory()
        ->suppressed(now()->subDay())
        ->create([
            'street_address' => 'Reinstated Court',
            'display_status' => 'Available',
            'modified_at' => now()->subHours(6),
        ]);

    $this->get(route('listings.index'))
        ->assertOk()
        ->assertSee($visibleListing->street_address)
        ->assertSee($expiredSuppression->street_address)
        ->assertDontSee($suppressedListing->street_address);

    $this->get(route('listings.show', $visibleListing))
        ->assertOk();

    $this->get(route('listings.show', $suppressedListing))
        ->assertNotFound();

    $this->get(route('listings.show', $expiredSuppression))
        ->assertOk();
});

test('listing detail page shows status history timeline', function (): void {
    $listing = Listing::factory()->create([
        'street_address' => '123 History Lane',
        'display_status' => 'Available',
    ]);

    // Create status history entries
    ListingStatusHistory::factory()->create([
        'listing_id' => $listing->id,
        'status_label' => 'Active',
        'status_code' => 'ACTIVE',
        'changed_at' => now()->subDays(30),
        'notes' => 'Listed for sale',
    ]);

    ListingStatusHistory::factory()->create([
        'listing_id' => $listing->id,
        'status_label' => 'Pending',
        'status_code' => 'PENDING',
        'changed_at' => now()->subDays(10),
        'notes' => 'Offer received',
    ]);

    ListingStatusHistory::factory()->create([
        'listing_id' => $listing->id,
        'status_label' => 'Available',
        'status_code' => 'ACTIVE',
        'changed_at' => now()->subDays(5),
        'notes' => 'Back on market',
    ]);

    $this->get(route('listings.show', $listing))
        ->assertOk()
        ->assertSee('Status history')
        ->assertSee('Active')
        ->assertSee('Pending')
        ->assertSee('Listed for sale')
        ->assertSee('Offer received')
        ->assertSee('Back on market');
});
