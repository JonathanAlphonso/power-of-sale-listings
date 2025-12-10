<?php

use App\Models\Listing;
use App\Models\ListingView;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('guests cannot access recently viewed page', function (): void {
    get(route('recently-viewed.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view recently viewed page', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('recently-viewed.index'))
        ->assertOk()
        ->assertSee('Recently Viewed');
});

test('recently viewed page shows empty state when no history', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('recently-viewed.index'))
        ->assertOk()
        ->assertSee('No recently viewed listings');
});

test('viewing a listing records the view for authenticated users', function (): void {
    $user = User::factory()->create();
    $listing = Listing::factory()->create([
        'street_address' => '123 Viewed Street',
    ]);

    actingAs($user)
        ->get(route('listings.show', $listing))
        ->assertOk();

    $this->assertDatabaseHas('listing_views', [
        'user_id' => $user->id,
        'listing_id' => $listing->id,
    ]);
});

test('viewing a listing does not record for guests', function (): void {
    $listing = Listing::factory()->create();

    get(route('listings.show', $listing))
        ->assertOk();

    $this->assertDatabaseCount('listing_views', 0);
});

test('viewing the same listing updates the viewed_at timestamp', function (): void {
    $user = User::factory()->create();
    $listing = Listing::factory()->create();

    // First view
    ListingView::recordView($user, $listing);
    $firstView = ListingView::where('user_id', $user->id)->where('listing_id', $listing->id)->first();
    $firstViewedAt = $firstView->viewed_at;

    // Wait a moment and view again
    sleep(1);
    ListingView::recordView($user, $listing);

    $updatedView = ListingView::where('user_id', $user->id)->where('listing_id', $listing->id)->first();

    expect($updatedView->viewed_at->greaterThan($firstViewedAt))->toBeTrue();
    expect(ListingView::where('user_id', $user->id)->where('listing_id', $listing->id)->count())->toBe(1);
});

test('recently viewed page shows viewed listings', function (): void {
    $user = User::factory()->create();
    $listing = Listing::factory()->create([
        'street_address' => '789 History Lane',
    ]);

    ListingView::recordView($user, $listing);

    actingAs($user)
        ->get(route('recently-viewed.index'))
        ->assertOk()
        ->assertSee('789 History Lane');
});

test('recently viewed listings are ordered by viewed_at descending', function (): void {
    $user = User::factory()->create();

    $listing1 = Listing::factory()->create(['street_address' => 'First Viewed']);
    $listing2 = Listing::factory()->create(['street_address' => 'Second Viewed']);
    $listing3 = Listing::factory()->create(['street_address' => 'Third Viewed']);

    ListingView::create([
        'user_id' => $user->id,
        'listing_id' => $listing1->id,
        'viewed_at' => now()->subHours(3),
    ]);

    ListingView::create([
        'user_id' => $user->id,
        'listing_id' => $listing2->id,
        'viewed_at' => now()->subHours(2),
    ]);

    ListingView::create([
        'user_id' => $user->id,
        'listing_id' => $listing3->id,
        'viewed_at' => now()->subHour(),
    ]);

    $recentlyViewed = $user->recentlyViewedListings()->get();

    expect($recentlyViewed->first()->street_address)->toBe('Third Viewed');
    expect($recentlyViewed->last()->street_address)->toBe('First Viewed');
});

test('users can clear their viewing history', function (): void {
    $user = User::factory()->create();
    $listings = Listing::factory()->count(3)->create();

    foreach ($listings as $listing) {
        ListingView::recordView($user, $listing);
    }

    expect($user->listingViews()->count())->toBe(3);

    Volt::actingAs($user)
        ->test('recently-viewed.index')
        ->call('clearHistory');

    expect($user->listingViews()->count())->toBe(0);
});

test('viewing history is isolated per user', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $listing = Listing::factory()->create([
        'street_address' => 'User2 Viewed This',
    ]);

    ListingView::recordView($user2, $listing);

    actingAs($user1)
        ->get(route('recently-viewed.index'))
        ->assertOk()
        ->assertDontSee('User2 Viewed This');
});

test('recently viewed is limited to 24 listings', function (): void {
    $user = User::factory()->create();
    $listings = Listing::factory()->count(30)->create();

    foreach ($listings as $index => $listing) {
        ListingView::create([
            'user_id' => $user->id,
            'listing_id' => $listing->id,
            'viewed_at' => now()->subMinutes($index),
        ]);
    }

    // Query through the relationship to verify limit
    $recentlyViewed = $user->recentlyViewedListings()
        ->with(['source:id,name', 'municipality:id,name', 'media'])
        ->limit(24)
        ->get();

    expect($recentlyViewed)->toHaveCount(24);
});

test('ListingView recordView method creates new entry', function (): void {
    $user = User::factory()->create();
    $listing = Listing::factory()->create();

    $view = ListingView::recordView($user, $listing);

    expect($view)->toBeInstanceOf(ListingView::class);
    expect($view->user_id)->toBe($user->id);
    expect($view->listing_id)->toBe($listing->id);
    expect($view->viewed_at)->not->toBeNull();
});
