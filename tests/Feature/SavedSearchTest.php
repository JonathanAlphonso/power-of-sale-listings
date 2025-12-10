<?php

use App\Models\Listing;
use App\Models\Municipality;
use App\Models\SavedSearch;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('guests cannot access saved searches', function (): void {
    get(route('saved-searches.index'))
        ->assertRedirect(route('login'));

    get(route('saved-searches.create'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view their saved searches', function (): void {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'name' => 'Toronto Condos',
    ]);

    actingAs($user)
        ->get(route('saved-searches.index'))
        ->assertOk()
        ->assertSee('Saved Searches')
        ->assertSee('Toronto Condos');
});

test('users cannot see other users saved searches', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $search = SavedSearch::factory()->create([
        'user_id' => $user2->id,
        'name' => 'Private Search',
    ]);

    actingAs($user1)
        ->get(route('saved-searches.index'))
        ->assertOk()
        ->assertDontSee('Private Search');
});

test('users can create a saved search', function (): void {
    $user = User::factory()->create();
    $municipality = Municipality::factory()->create(['name' => 'Toronto']);

    Listing::factory()->create([
        'municipality_id' => $municipality->id,
        'property_type' => 'Condo',
        'list_price' => 500000,
    ]);

    Volt::actingAs($user)
        ->test('saved-searches.create')
        ->set('name', 'Toronto Condos Under 600K')
        ->set('notification_channel', 'email')
        ->set('notification_frequency', 'daily')
        ->set('municipalityId', (string) $municipality->id)
        ->set('propertyType', 'Condo')
        ->set('maxPrice', '600000')
        ->call('save')
        ->assertRedirect(route('saved-searches.index'));

    $this->assertDatabaseHas('saved_searches', [
        'user_id' => $user->id,
        'name' => 'Toronto Condos Under 600K',
        'notification_channel' => 'email',
        'notification_frequency' => 'daily',
    ]);
});

test('saved search create shows matching count', function (): void {
    $user = User::factory()->create();

    Listing::factory()->count(5)->create([
        'property_type' => 'Condo',
        'list_price' => 400000,
    ]);

    Listing::factory()->count(3)->create([
        'property_type' => 'House',
        'list_price' => 800000,
    ]);

    Volt::actingAs($user)
        ->test('saved-searches.create')
        ->set('propertyType', 'Condo')
        ->assertSee('5 matching listings');
});

test('users can edit their saved search', function (): void {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'name' => 'Old Name',
        'notification_frequency' => 'daily',
    ]);

    Volt::actingAs($user)
        ->test('saved-searches.edit', ['savedSearch' => $search])
        ->assertSet('name', 'Old Name')
        ->set('name', 'New Name')
        ->set('notification_frequency', 'weekly')
        ->call('save')
        ->assertRedirect(route('saved-searches.index'));

    $this->assertDatabaseHas('saved_searches', [
        'id' => $search->id,
        'name' => 'New Name',
        'notification_frequency' => 'weekly',
    ]);
});

test('users cannot edit other users saved searches', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $search = SavedSearch::factory()->create([
        'user_id' => $user2->id,
    ]);

    actingAs($user1)
        ->get(route('saved-searches.edit', $search))
        ->assertForbidden();
});

test('users can delete their saved search', function (): void {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'name' => 'To Delete',
    ]);

    Volt::actingAs($user)
        ->test('saved-searches.index')
        ->assertSee('To Delete')
        ->call('confirmDelete', $search->id)
        ->call('deleteSearch')
        ->assertDontSee('To Delete');

    $this->assertDatabaseMissing('saved_searches', ['id' => $search->id]);
});

test('users can toggle saved search active status', function (): void {
    $user = User::factory()->create();
    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    Volt::actingAs($user)
        ->test('saved-searches.index')
        ->call('toggleActive', $search->id);

    $this->assertDatabaseHas('saved_searches', [
        'id' => $search->id,
        'is_active' => false,
    ]);
});

test('saved search validates required name', function (): void {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('saved-searches.create')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

test('saved search validates name max length', function (): void {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('saved-searches.create')
        ->set('name', str_repeat('a', 101))
        ->call('save')
        ->assertHasErrors(['name']);
});

test('saved search pre-populates from query string', function (): void {
    $user = User::factory()->create();
    $municipality = Municipality::factory()->create(['name' => 'Ottawa']);

    Listing::factory()->create([
        'municipality_id' => $municipality->id,
    ]);

    actingAs($user)
        ->get(route('saved-searches.create', [
            'q' => 'downtown',
            'municipality' => $municipality->id,
            'type' => 'Condo',
            'min_price' => '200000',
            'max_price' => '500000',
        ]))
        ->assertOk()
        ->assertSee('downtown');
});
