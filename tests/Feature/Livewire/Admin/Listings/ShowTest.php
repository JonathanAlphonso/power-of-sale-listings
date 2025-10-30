<?php

use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\ListingStatusHistory;
use App\Models\User;
use Livewire\Volt\Volt;

test('guests are redirected from the listing detail view', function (): void {
    $listing = Listing::factory()->create();

    $this->get(route('admin.listings.show', $listing))
        ->assertRedirect(route('login'));
});

test('subscribers cannot access the listing detail view when an admin exists', function (): void {
    User::factory()->admin()->create();

    $listing = Listing::factory()->create();
    $subscriber = User::factory()->create();

    $this->actingAs($subscriber)
        ->get(route('admin.listings.show', $listing))
        ->assertForbidden();
});

test('admins can review listing metadata, photos, and change history', function (): void {
    $admin = User::factory()->admin()->create();

    $listing = Listing::factory()->create([
        'street_address' => '123 Harbour Street',
        'city' => 'Toronto',
        'display_status' => 'Available',
        'mls_number' => 'C1234567',
        'list_price' => 725000,
    ]);

    $primaryMedia = ListingMedia::factory()->for($listing)->create([
        'label' => 'Front elevation',
        'position' => 0,
        'is_primary' => true,
        'preview_url' => 'https://example.com/photos/front.jpg',
    ]);

    ListingMedia::factory()->for($listing)->create([
        'label' => 'Kitchen',
        'position' => 1,
        'is_primary' => false,
        'preview_url' => 'https://example.com/photos/kitchen.jpg',
    ]);

    $historyEntry = ListingStatusHistory::factory()->for($listing)->create([
        'status_label' => 'Conditionally Sold',
        'status_code' => 'COND',
        'changed_at' => now()->subDay(),
        'notes' => 'Offer accepted, pending conditions.',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.listings.show', $listing))
        ->assertOk()
        ->assertSee('Listing metadata');

    Volt::test('admin.listings.show', ['listing' => $listing->getRouteKey()])
        ->assertSee('Listing metadata')
        ->assertSee(strtoupper($listing->street_address))
        ->assertSee($listing->mls_number)
        ->assertSee('$725,000')
        ->assertSee('Media gallery')
        ->assertSee($primaryMedia->label)
        ->assertSee('Change history')
        ->assertSee($historyEntry->status_label)
        ->assertSee($historyEntry->notes);
});
