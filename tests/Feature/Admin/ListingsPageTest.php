<?php

use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\ListingSuppression;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Support\Carbon;
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

test('admins can suppress and unsuppress listings with audit logging', function (): void {
    $admin = User::factory()->admin()->create();
    $listing = Listing::factory()->create([
        'list_price' => 650000,
        'display_status' => 'Available',
        'modified_at' => now(),
    ]);

    $expiresAt = Carbon::now()->addDays(5)->setSecond(0)->setMicrosecond(0);

    $this->actingAs($admin);

    $component = Volt::test('admin.listings.index')
        ->call('selectListing', $listing->id)
        ->set('suppressionForm.reason', 'Inaccurate room counts')
        ->set('suppressionForm.notes', 'Awaiting broker confirmation')
        ->set('suppressionForm.expires_at', $expiresAt->format('Y-m-d\TH:i'));

    $component->call('suppressSelected')->assertHasNoErrors();

    $listing->refresh();

    expect($listing->isSuppressed())->toBeTrue();
    expect($listing->suppression_reason)->toBe('Inaccurate room counts');
    expect($listing->suppressed_by_user_id)->toBe($admin->id);
    expect($listing->suppression_expires_at?->equalTo($expiresAt))->toBeTrue();

    $suppression = ListingSuppression::query()->where('listing_id', $listing->id)->first();

    expect($suppression)->not->toBeNull();
    expect($suppression->reason)->toBe('Inaccurate room counts');
    expect($suppression->notes)->toBe('Awaiting broker confirmation');
    expect($suppression->expires_at?->equalTo($expiresAt))->toBeTrue();
    expect($suppression->user_id)->toBe($admin->id);
    expect(AuditLog::query()->where('action', 'listing.suppressed')->count())->toBe(1);

    $component
        ->set('unsuppressionForm.reason', 'Details verified')
        ->set('unsuppressionForm.notes', 'Broker provided updated MLS remarks.')
        ->call('unsuppressSelected')
        ->assertHasNoErrors();

    $listing->refresh();
    $suppression->refresh();

    expect($listing->isSuppressed())->toBeFalse();
    expect($listing->suppressed_at)->toBeNull();
    expect($listing->suppression_reason)->toBeNull();
    expect($listing->suppressed_by_user_id)->toBeNull();

    expect($suppression->released_at)->not->toBeNull();
    expect($suppression->release_reason)->toBe('Details verified');
    expect($suppression->release_notes)->toBe('Broker provided updated MLS remarks.');
    expect($suppression->release_user_id)->toBe($admin->id);

    expect(AuditLog::query()->where('action', 'listing.unsuppressed')->count())->toBe(1);
});
