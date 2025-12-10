<?php

use App\Models\Listing;
use App\Models\User;
use App\Models\UserFavorite;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('guests cannot access favorites page', function (): void {
    get(route('favorites.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view their favorites page', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('favorites.index'))
        ->assertOk()
        ->assertSee('My Favorites');
});

test('favorites page shows empty state when no favorites', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('favorites.index'))
        ->assertOk()
        ->assertSee('No favorites yet');
});

test('favorites page shows favorited listings', function (): void {
    $user = User::factory()->create();
    $listing = Listing::factory()->create([
        'street_address' => '123 Test Street',
        'list_price' => 450000,
    ]);

    UserFavorite::create([
        'user_id' => $user->id,
        'listing_id' => $listing->id,
    ]);

    actingAs($user)
        ->get(route('favorites.index'))
        ->assertOk()
        ->assertSee('123 Test Street');
});

test('users can add a listing to favorites via toggle button', function (): void {
    $user = User::factory()->create();
    $listing = Listing::factory()->create();

    Volt::actingAs($user)
        ->test('favorites.toggle-button', ['listingId' => $listing->id])
        ->assertSet('isFavorited', false)
        ->call('toggle')
        ->assertSet('isFavorited', true);

    $this->assertDatabaseHas('user_favorites', [
        'user_id' => $user->id,
        'listing_id' => $listing->id,
    ]);
});

test('users can remove a listing from favorites via toggle button', function (): void {
    $user = User::factory()->create();
    $listing = Listing::factory()->create();

    UserFavorite::create([
        'user_id' => $user->id,
        'listing_id' => $listing->id,
    ]);

    Volt::actingAs($user)
        ->test('favorites.toggle-button', ['listingId' => $listing->id])
        ->assertSet('isFavorited', true)
        ->call('toggle')
        ->assertSet('isFavorited', false);

    $this->assertDatabaseMissing('user_favorites', [
        'user_id' => $user->id,
        'listing_id' => $listing->id,
    ]);
});

test('users can remove favorite from favorites page', function (): void {
    $user = User::factory()->create();
    $listing = Listing::factory()->create([
        'street_address' => '456 Remove Street',
    ]);

    UserFavorite::create([
        'user_id' => $user->id,
        'listing_id' => $listing->id,
    ]);

    Volt::actingAs($user)
        ->test('favorites.index')
        ->assertSee('456 Remove Street')
        ->call('confirmRemove', $listing->id)
        ->call('removeFavorite')
        ->assertDontSee('456 Remove Street');

    $this->assertDatabaseMissing('user_favorites', [
        'user_id' => $user->id,
        'listing_id' => $listing->id,
    ]);
});

test('users can remove all favorites', function (): void {
    $user = User::factory()->create();
    $listings = Listing::factory()->count(3)->create();

    foreach ($listings as $listing) {
        UserFavorite::create([
            'user_id' => $user->id,
            'listing_id' => $listing->id,
        ]);
    }

    expect($user->favorites()->count())->toBe(3);

    Volt::actingAs($user)
        ->test('favorites.index')
        ->call('removeAll');

    expect($user->favorites()->count())->toBe(0);
});

test('user favorites are isolated from other users', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $listing = Listing::factory()->create([
        'street_address' => 'User2 Private Listing',
    ]);

    UserFavorite::create([
        'user_id' => $user2->id,
        'listing_id' => $listing->id,
    ]);

    actingAs($user1)
        ->get(route('favorites.index'))
        ->assertOk()
        ->assertDontSee('User2 Private Listing');
});

test('favorites show total value of all favorited listings', function (): void {
    $user = User::factory()->create();

    $listing1 = Listing::factory()->create(['list_price' => 300000]);
    $listing2 = Listing::factory()->create(['list_price' => 400000]);

    UserFavorite::create(['user_id' => $user->id, 'listing_id' => $listing1->id]);
    UserFavorite::create(['user_id' => $user->id, 'listing_id' => $listing2->id]);

    Volt::actingAs($user)
        ->test('favorites.index')
        ->assertSee('$700,000');
});

test('favorite toggle button shows outline heart when not favorited', function (): void {
    $user = User::factory()->create();
    $listing = Listing::factory()->create();

    Volt::actingAs($user)
        ->test('favorites.toggle-button', ['listingId' => $listing->id])
        ->assertSet('isFavorited', false);
});

test('favorite toggle button shows solid heart when favorited', function (): void {
    $user = User::factory()->create();
    $listing = Listing::factory()->create();

    UserFavorite::create([
        'user_id' => $user->id,
        'listing_id' => $listing->id,
    ]);

    Volt::actingAs($user)
        ->test('favorites.toggle-button', ['listingId' => $listing->id])
        ->assertSet('isFavorited', true);
});

test('hasFavorited method works correctly', function (): void {
    $user = User::factory()->create();
    $listing1 = Listing::factory()->create();
    $listing2 = Listing::factory()->create();

    UserFavorite::create([
        'user_id' => $user->id,
        'listing_id' => $listing1->id,
    ]);

    expect($user->hasFavorited($listing1))->toBeTrue();
    expect($user->hasFavorited($listing2))->toBeFalse();
    expect($user->hasFavorited($listing1->id))->toBeTrue();
    expect($user->hasFavorited($listing2->id))->toBeFalse();
});
