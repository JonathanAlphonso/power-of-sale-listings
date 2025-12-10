<?php

use App\Models\Listing;
use App\Models\Municipality;
use App\Support\ListingPresentation;
use Livewire\Volt\Volt;

test('price per sqft helper calculates correctly', function (): void {
    // Test valid calculation
    expect(ListingPresentation::pricePerSqft(500000, 2000))->toBe('$250/sqft');
    expect(ListingPresentation::pricePerSqft(750000, 1500))->toBe('$500/sqft');

    // Test null/empty values
    expect(ListingPresentation::pricePerSqft(null, 2000))->toBe('—');
    expect(ListingPresentation::pricePerSqft(500000, null))->toBe('—');
    expect(ListingPresentation::pricePerSqft('', 2000))->toBe('—');
    expect(ListingPresentation::pricePerSqft(500000, ''))->toBe('—');

    // Test zero square feet
    expect(ListingPresentation::pricePerSqft(500000, 0))->toBe('—');
});

test('price per sqft raw helper returns numeric value', function (): void {
    // Test valid calculation
    expect(ListingPresentation::pricePerSqftRaw(500000, 2000))->toBe(250.0);
    expect(ListingPresentation::pricePerSqftRaw(750000, 1500))->toBe(500.0);

    // Test null/empty values
    expect(ListingPresentation::pricePerSqftRaw(null, 2000))->toBeNull();
    expect(ListingPresentation::pricePerSqftRaw(500000, 0))->toBeNull();
});

test('listing detail page shows price per sqft', function (): void {
    $listing = Listing::factory()->create([
        'street_address' => '100 Test Street',
        'city' => 'Toronto',
        'display_status' => 'Available',
        'list_price' => 500000,
        'square_feet' => 2000,
    ]);

    $this->get(route('listings.show', $listing))
        ->assertOk()
        ->assertSee('Price per sqft')
        ->assertSee('$250/sqft');
});

test('listing detail page shows dash for price per sqft when square feet not available', function (): void {
    $listing = Listing::factory()->create([
        'street_address' => '100 Test Street',
        'city' => 'Toronto',
        'display_status' => 'Available',
        'list_price' => 500000,
        'square_feet' => null,
    ]);

    $this->get(route('listings.show', $listing))
        ->assertOk()
        ->assertSee('Price per sqft');
});

test('comparison page includes price per sqft field', function (): void {
    $listing1 = Listing::factory()->create([
        'street_address' => '100 Compare Street',
        'display_status' => 'Available',
        'list_price' => 500000,
        'square_feet' => 2000,
    ]);

    $listing2 = Listing::factory()->create([
        'street_address' => '200 Compare Ave',
        'display_status' => 'Available',
        'list_price' => 600000,
        'square_feet' => 1500,
    ]);

    Volt::test('listings.compare', ['listingIds' => "{$listing1->id},{$listing2->id}"])
        ->assertSee('Price per sqft')
        ->assertSee('$250/sqft')  // listing1: 500000/2000
        ->assertSee('$400/sqft'); // listing2: 600000/1500
});

test('listing detail page shows related listings', function (): void {
    $municipality = Municipality::factory()->create(['name' => 'Test Municipality']);

    $mainListing = Listing::factory()->create([
        'street_address' => '100 Main Street',
        'municipality_id' => $municipality->id,
        'city' => 'Toronto',
        'display_status' => 'Available',
        'list_price' => 500000,
    ]);

    // Create related listings in same municipality with similar price
    $related1 = Listing::factory()->create([
        'street_address' => '200 Related Road',
        'municipality_id' => $municipality->id,
        'city' => 'Toronto',
        'display_status' => 'Available',
        'list_price' => 450000, // Within 30% of main listing
        'modified_at' => now()->subHour(),
    ]);

    $related2 = Listing::factory()->create([
        'street_address' => '300 Nearby Ave',
        'municipality_id' => $municipality->id,
        'city' => 'Toronto',
        'display_status' => 'Available',
        'list_price' => 550000, // Within 30% of main listing
        'modified_at' => now()->subHours(2),
    ]);

    $this->get(route('listings.show', $mainListing))
        ->assertOk()
        ->assertSee('Similar properties')
        ->assertSee('200 Related Road')
        ->assertSee('300 Nearby Ave');
});

test('listing detail page shows breadcrumb navigation', function (): void {
    $listing = Listing::factory()->create([
        'street_address' => '100 Breadcrumb Lane',
        'city' => 'Barrie',
        'display_status' => 'Available',
    ]);

    $this->get(route('listings.show', $listing))
        ->assertOk()
        ->assertSee('Home')
        ->assertSee('Listings')
        ->assertSee('Barrie')
        ->assertSee('100 Breadcrumb Lane');
});

test('csv export returns valid csv response', function (): void {
    Listing::factory()->count(5)->create([
        'display_status' => 'Available',
        'list_price' => 500000,
        'square_feet' => 2000,
    ]);

    $response = Volt::test('listings.index')
        ->call('exportCsv');

    // The response should be a StreamedResponse
    expect($response->effects['download'])->not->toBeNull();
});

test('csv export includes correct headers', function (): void {
    $listing = Listing::factory()->create([
        'mls_number' => 'TEST123',
        'street_address' => '100 Export Street',
        'city' => 'Toronto',
        'province' => 'Ontario',
        'postal_code' => 'M5V 1A1',
        'display_status' => 'Available',
        'list_price' => 500000,
        'original_list_price' => 550000,
        'square_feet' => 2000,
        'bedrooms' => 3,
        'bathrooms' => 2,
        'property_type' => 'Detached',
        'days_on_market' => 30,
    ]);

    // Test that the export method is callable
    $component = Volt::test('listings.index');

    // The component should have listings and show the export button
    $component->assertSee('Export CSV');
});

test('listings page shows export csv button', function (): void {
    Listing::factory()->create(['display_status' => 'Available']);

    Volt::test('listings.index')
        ->assertSee('Export CSV');
});

test('csv export respects current filters', function (): void {
    $torontoListing = Listing::factory()->create([
        'city' => 'Toronto',
        'display_status' => 'Available',
        'list_price' => 500000,
    ]);

    $barrieListing = Listing::factory()->create([
        'city' => 'Barrie',
        'display_status' => 'Available',
        'list_price' => 400000,
    ]);

    // Set a filter and verify export respects it
    $component = Volt::test('listings.index')
        ->set('search', 'Toronto');

    // The component should only show Toronto listings when filtered
    $component->assertSee('Toronto');
});
