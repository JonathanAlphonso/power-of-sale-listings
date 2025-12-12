<?php

use App\Models\Listing;

test('guests can browse current listings', function (): void {
    $torontoListing = Listing::factory()->create([
        'street_address' => '101 Harbour Street',
        'city' => 'Toronto',
        'display_status' => 'Active',
        'property_style' => 'Detached',
        'list_price' => 625000,
        'modified_at' => now(),
    ]);

    Listing::factory()->create([
        'street_address' => '202 Wellington Street',
        'display_status' => 'Active',
        'property_style' => 'Detached',
        'modified_at' => now()->subDay(),
    ]);

    $this->get(route('listings.index'))
        ->assertOk()
        ->assertSee('Current listings')
        ->assertSee($torontoListing->street_address)
        ->assertSee('$625,000')
        ->assertSee('202 Wellington Street')
        ->assertSee($torontoListing->url, false);
});

test('listings pagination links are rendered', function (): void {
    Listing::factory()->count(15)->create([
        'display_status' => 'Active',
        'property_style' => 'Detached',
        'modified_at' => now(),
    ]);

    $response = $this->get(route('listings.index'));

    $response
        ->assertOk()
        // Livewire pagination uses wire:click instead of href links
        ->assertSee('gotoPage(2', false);
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

    $this->get($listing->url)
        ->assertOk()
        ->assertSee('$675,000')
        ->assertSee('34 LAMPMAN LANE')
        ->assertSee('Barrie (Letitia Heights), Ontario L4N5B1')
        ->assertSee('MLS® Number: S12426397');
});

test('suppressed listings are hidden from the public catalog', function (): void {
    $visibleListing = Listing::factory()->create([
        'street_address' => '101 Harbour Street',
        'city' => 'Toronto',
        'display_status' => 'Active',
        'property_style' => 'Detached',
        'modified_at' => now(),
    ]);

    $suppressedListing = Listing::factory()
        ->suppressed()
        ->create([
            'street_address' => 'Hidden Lane',
            'display_status' => 'Active',
            'property_style' => 'Detached',
            'modified_at' => now()->subDay(),
        ]);

    $expiredSuppression = Listing::factory()
        ->suppressed(now()->subDay())
        ->create([
            'street_address' => 'Reinstated Court',
            'display_status' => 'Active',
            'property_style' => 'Detached',
            'modified_at' => now()->subHours(6),
        ]);

    $this->get(route('listings.index'))
        ->assertOk()
        ->assertSee($visibleListing->street_address)
        ->assertSee($expiredSuppression->street_address)
        ->assertDontSee($suppressedListing->street_address);

    $this->get($visibleListing->url)
        ->assertOk();

    $this->get($suppressedListing->url)
        ->assertNotFound();

    $this->get($expiredSuppression->url)
        ->assertOk();
});

test('listings generate SEO-friendly slugs with address on creation', function (): void {
    $listing = Listing::factory()->create([
        'street_number' => '236',
        'unit_number' => '208',
        'street_name' => 'Albion Road',
        'city' => 'Etobicoke',
        'display_status' => 'Available',
    ]);

    // Slug should be address only (no ID), with unit number first (just digits)
    expect($listing->slug)->toBe('208-236-albion-road-etobicoke');
    // URL should have slug/{id} format
    expect($listing->url)->toContain('/208-236-albion-road-etobicoke/'.$listing->id);
});

test('listing URLs include the address slug', function (): void {
    $listing = Listing::factory()->create([
        'street_number' => '100',
        'unit_number' => null,
        'street_name' => 'Main Street',
        'city' => 'Toronto',
        'display_status' => 'Available',
    ]);

    $url = $listing->url;

    expect($url)->toContain($listing->slug);
    expect($url)->toContain('100-main-street-toronto/'.$listing->id);
});

test('listing detail page is accessible via slug URL', function (): void {
    $listing = Listing::factory()->create([
        'street_number' => '55',
        'unit_number' => null,
        'street_name' => 'Queen Street',
        'street_address' => '55 Queen Street',
        'city' => 'Hamilton',
        'display_status' => 'Available',
        'list_price' => 500000,
    ]);

    $this->get('/listings/'.$listing->slug.'/'.$listing->id)
        ->assertOk()
        ->assertSee('$500,000');
});

test('old ID-based URLs redirect to the new slug URL', function (): void {
    $listing = Listing::factory()->create([
        'street_number' => '99',
        'unit_number' => null,
        'street_name' => 'King Street',
        'city' => 'Ottawa',
        'display_status' => 'Available',
    ]);

    // Accessing by just the ID should redirect to the full slug URL
    $this->get('/listings/'.$listing->id)
        ->assertRedirect($listing->url);
});

test('listing slugs update when address fields change', function (): void {
    $listing = Listing::factory()->create([
        'street_number' => '10',
        'unit_number' => null,
        'street_name' => 'First Avenue',
        'city' => 'Mississauga',
        'display_status' => 'Available',
    ]);

    $originalSlug = $listing->slug;
    expect($originalSlug)->toBe('10-first-avenue-mississauga');

    $listing->update([
        'street_name' => 'Second Avenue',
    ]);

    expect($listing->fresh()->slug)->not->toBe($originalSlug);
    expect($listing->fresh()->slug)->toBe('10-second-avenue-mississauga');
});

test('listing slug handles missing address components gracefully', function (): void {
    $listing = Listing::factory()->create([
        'street_number' => null,
        'unit_number' => null,
        'street_name' => null,
        'city' => 'Toronto',
        'display_status' => 'Available',
    ]);

    expect($listing->slug)->toBe('toronto');
});

test('listing slug falls back to "listing" when no address info available', function (): void {
    $listing = Listing::factory()->create([
        'street_number' => null,
        'unit_number' => null,
        'street_name' => null,
        'city' => null,
        'display_status' => 'Available',
    ]);

    expect($listing->slug)->toBe('listing');
});
