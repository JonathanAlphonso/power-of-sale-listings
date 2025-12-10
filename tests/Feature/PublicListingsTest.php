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

// Quick Filters Tests
test('quick filters are displayed on listings page', function (): void {
    Listing::factory()->create(['display_status' => 'Available']);

    Livewire\Volt\Volt::test('listings.index')
        ->assertSee('Quick filters:')
        ->assertSee('New This Week')
        ->assertSee('Price Reduced')
        ->assertSee('Under $300K')
        ->assertSee('Under $500K')
        ->assertSee('Houses Only')
        ->assertSee('Condos Only');
});

test('new this week quick filter shows only listings from the past week', function (): void {
    $newListing = Listing::factory()->create([
        'street_address' => '123 New Street',
        'display_status' => 'Available',
        'listed_at' => now()->subDays(3),
    ]);

    $oldListing = Listing::factory()->create([
        'street_address' => '456 Old Avenue',
        'display_status' => 'Available',
        'listed_at' => now()->subDays(10),
    ]);

    Livewire\Volt\Volt::test('listings.index')
        ->call('applyQuickFilter', 'new_this_week')
        ->assertSet('quickFilter', 'new_this_week')
        ->assertSee('123 New Street')
        ->assertDontSee('456 Old Avenue');
});

test('price reduced quick filter shows only listings with price reductions', function (): void {
    $reducedListing = Listing::factory()->create([
        'street_address' => '100 Discount Lane',
        'display_status' => 'Available',
        'list_price' => 400000,
        'original_list_price' => 500000,
    ]);

    $normalListing = Listing::factory()->create([
        'street_address' => '200 Full Price Blvd',
        'display_status' => 'Available',
        'list_price' => 500000,
        'original_list_price' => 500000,
    ]);

    $noOriginalPrice = Listing::factory()->create([
        'street_address' => '300 No Original St',
        'display_status' => 'Available',
        'list_price' => 450000,
        'original_list_price' => null,
    ]);

    Livewire\Volt\Volt::test('listings.index')
        ->call('applyQuickFilter', 'price_reduced')
        ->assertSet('quickFilter', 'price_reduced')
        ->assertSee('100 Discount Lane')
        ->assertDontSee('200 Full Price Blvd')
        ->assertDontSee('300 No Original St');
});

test('under 300k quick filter sets max price to 300000', function (): void {
    $cheapListing = Listing::factory()->create([
        'street_address' => '10 Budget Way',
        'display_status' => 'Available',
        'list_price' => 250000,
    ]);

    $expensiveListing = Listing::factory()->create([
        'street_address' => '20 Luxury Ave',
        'display_status' => 'Available',
        'list_price' => 400000,
    ]);

    Livewire\Volt\Volt::test('listings.index')
        ->call('applyQuickFilter', 'under_300k')
        ->assertSet('quickFilter', 'under_300k')
        ->assertSet('maxPrice', '300000')
        ->assertSee('10 Budget Way')
        ->assertDontSee('20 Luxury Ave');
});

test('under 500k quick filter sets max price to 500000', function (): void {
    $midRangeListing = Listing::factory()->create([
        'street_address' => '15 MidRange Ct',
        'display_status' => 'Available',
        'list_price' => 450000,
    ]);

    $expensiveListing = Listing::factory()->create([
        'street_address' => '25 Premium Blvd',
        'display_status' => 'Available',
        'list_price' => 600000,
    ]);

    Livewire\Volt\Volt::test('listings.index')
        ->call('applyQuickFilter', 'under_500k')
        ->assertSet('quickFilter', 'under_500k')
        ->assertSet('maxPrice', '500000')
        ->assertSee('15 MidRange Ct')
        ->assertDontSee('25 Premium Blvd');
});

test('houses only quick filter sets property type to Detached', function (): void {
    $house = Listing::factory()->create([
        'street_address' => '50 Home Street',
        'display_status' => 'Available',
        'property_type' => 'Detached',
    ]);

    $condo = Listing::factory()->create([
        'street_address' => '60 Condo Tower',
        'display_status' => 'Available',
        'property_type' => 'Condo Apt',
    ]);

    Livewire\Volt\Volt::test('listings.index')
        ->call('applyQuickFilter', 'houses_only')
        ->assertSet('quickFilter', 'houses_only')
        ->assertSet('propertyType', 'Detached')
        ->assertSee('50 Home Street')
        ->assertDontSee('60 Condo Tower');
});

test('condos only quick filter sets property type to Condo Apt', function (): void {
    $house = Listing::factory()->create([
        'street_address' => '70 House Lane',
        'display_status' => 'Available',
        'property_type' => 'Detached',
    ]);

    $condo = Listing::factory()->create([
        'street_address' => '80 Skyline Tower',
        'display_status' => 'Available',
        'property_type' => 'Condo Apt',
    ]);

    Livewire\Volt\Volt::test('listings.index')
        ->call('applyQuickFilter', 'condos_only')
        ->assertSet('quickFilter', 'condos_only')
        ->assertSet('propertyType', 'Condo Apt')
        ->assertSee('80 Skyline Tower')
        ->assertDontSee('70 House Lane');
});

test('clicking same quick filter again clears it and resets filters', function (): void {
    Listing::factory()->create(['display_status' => 'Available', 'list_price' => 250000]);
    Listing::factory()->create(['display_status' => 'Available', 'list_price' => 400000]);

    Livewire\Volt\Volt::test('listings.index')
        ->call('applyQuickFilter', 'under_300k')
        ->assertSet('quickFilter', 'under_300k')
        ->assertSet('maxPrice', '300000')
        ->call('applyQuickFilter', 'under_300k')
        ->assertSet('quickFilter', '')
        ->assertSet('maxPrice', '');
});

test('quick filter is persisted in URL', function (): void {
    Listing::factory()->create(['display_status' => 'Available', 'list_price' => 250000]);

    // Load page with quick filter in URL
    $this->get(route('listings.index', ['quick' => 'under_300k']))
        ->assertOk()
        ->assertSee('Under $300K');

    Livewire\Volt\Volt::test('listings.index', ['quickFilter' => 'under_300k'])
        ->assertSet('quickFilter', 'under_300k');
});

test('applying quick filter resets other filters', function (): void {
    Listing::factory()->create([
        'display_status' => 'Available',
        'property_type' => 'Detached',
        'list_price' => 250000,
    ]);

    Livewire\Volt\Volt::test('listings.index')
        ->set('search', 'test search')
        ->set('minPrice', '100000')
        ->set('minBedrooms', '3')
        ->call('applyQuickFilter', 'under_300k')
        ->assertSet('quickFilter', 'under_300k')
        ->assertSet('search', '')
        ->assertSet('minPrice', '')
        ->assertSet('minBedrooms', '');
});

test('hasActiveFilters returns true when quick filter is set', function (): void {
    Listing::factory()->create(['display_status' => 'Available']);

    $component = Livewire\Volt\Volt::test('listings.index')
        ->assertSet('quickFilter', '');

    // Get the hasActiveFilters computed property value
    expect($component->get('hasActiveFilters'))->toBeFalse();

    $component->call('applyQuickFilter', 'new_this_week');

    expect($component->get('hasActiveFilters'))->toBeTrue();
});

// Empty State Tests
test('empty state shows filter suggestions when filters are active', function (): void {
    // Create no listings
    Livewire\Volt\Volt::test('listings.index')
        ->set('search', 'nonexistent property')
        ->assertSee('No listings match your filters')
        ->assertSee('Try these searches')
        ->assertSee('Clear all filters')
        ->assertSee('New This Week')
        ->assertSee('Price Reduced');
});

test('empty state shows browse by category when no filters are active', function (): void {
    // No listings in database
    Livewire\Volt\Volt::test('listings.index')
        ->assertSee('No listings match your filters')
        ->assertSee('Browse by category')
        ->assertSee('New This Week')
        ->assertSee('Under $300K');
});

test('authenticated users see notification option in empty state', function (): void {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user);

    Livewire\Volt\Volt::test('listings.index')
        ->set('search', 'nonexistent')
        ->assertSee('Get notified when matching listings appear');
});

test('guests do not see notification option in empty state', function (): void {
    Livewire\Volt\Volt::test('listings.index')
        ->set('search', 'nonexistent')
        ->assertDontSee('Get notified when matching listings appear');
});

test('quick filter buttons in empty state exclude currently active filter', function (): void {
    // The empty state section (after "Try these searches") should exclude the active filter
    // But the Quick Filters bar at the top still shows all filters with the active one highlighted
    // So we check that the filter is excluded from the suggestions section by counting occurrences
    $component = Livewire\Volt\Volt::test('listings.index')
        ->call('applyQuickFilter', 'under_300k')
        ->assertSee('No listings match your filters')
        ->assertSee('Try these searches');

    // The "Under $300K" should only appear once (in the Quick Filters bar, not in the suggestions)
    // The Quick Filters bar shows it as an active filter, but the suggestions should exclude it
    $html = $component->html();

    // Count how many times "Under $300K" appears - should be 1 (just in the quick filters bar)
    $count = substr_count($html, 'Under $300K');
    expect($count)->toBe(1, 'Active filter should appear once in Quick Filters bar but not in empty state suggestions');
});
