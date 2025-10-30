<?php

use App\Models\Listing;

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
    Listing::factory()->count(15)->create([
        'display_status' => 'Available',
        'modified_at' => now(),
    ]);

    $response = $this->get(route('listings.index'));

    $response
        ->assertOk()
        ->assertSee('?page=2', false);
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
