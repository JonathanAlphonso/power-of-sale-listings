<?php

use App\Models\SavedSearch;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('guests cannot access notification settings', function (): void {
    get(route('notifications.edit'))
        ->assertRedirect(route('login'));
});

test('authenticated users can access notification settings', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('notifications.edit'))
        ->assertOk()
        ->assertSee('Notifications')
        ->assertSee('Saved Search Notifications');
});

test('notification settings shows saved searches', function (): void {
    $user = User::factory()->create();

    SavedSearch::factory()->create([
        'user_id' => $user->id,
        'name' => 'Toronto Condos',
        'notification_frequency' => 'daily',
        'is_active' => true,
    ]);

    actingAs($user)
        ->get(route('notifications.edit'))
        ->assertOk()
        ->assertSee('Toronto Condos')
        ->assertSee('Active');
});

test('notification settings shows empty state when no saved searches', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('notifications.edit'))
        ->assertOk()
        ->assertSee('No saved searches')
        ->assertSee('Create saved search');
});

test('users can toggle search notification status', function (): void {
    $user = User::factory()->create();

    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    Volt::actingAs($user)
        ->test('settings.notifications')
        ->call('toggleSearchActive', $search->id);

    $this->assertDatabaseHas('saved_searches', [
        'id' => $search->id,
        'is_active' => false,
    ]);
});

test('users can update notification channel', function (): void {
    $user = User::factory()->create();

    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'notification_channel' => 'email',
    ]);

    Volt::actingAs($user)
        ->test('settings.notifications')
        ->call('updateSearchChannel', $search->id, 'none');

    $this->assertDatabaseHas('saved_searches', [
        'id' => $search->id,
        'notification_channel' => 'none',
    ]);
});

test('users can update notification frequency', function (): void {
    $user = User::factory()->create();

    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'notification_frequency' => 'instant',
    ]);

    Volt::actingAs($user)
        ->test('settings.notifications')
        ->call('updateSearchFrequency', $search->id, 'weekly');

    $this->assertDatabaseHas('saved_searches', [
        'id' => $search->id,
        'notification_frequency' => 'weekly',
    ]);
});

test('users cannot update other users search settings', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $search = SavedSearch::factory()->create([
        'user_id' => $user2->id,
        'notification_frequency' => 'instant',
    ]);

    Volt::actingAs($user1)
        ->test('settings.notifications')
        ->call('updateSearchFrequency', $search->id, 'weekly');

    // Should remain unchanged
    $this->assertDatabaseHas('saved_searches', [
        'id' => $search->id,
        'notification_frequency' => 'instant',
    ]);
});

test('users can pause all notifications', function (): void {
    $user = User::factory()->create();

    SavedSearch::factory()->count(3)->create([
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    Volt::actingAs($user)
        ->test('settings.notifications')
        ->call('pauseAllNotifications');

    expect(SavedSearch::where('user_id', $user->id)->where('is_active', true)->count())->toBe(0);
});

test('users can resume all notifications', function (): void {
    $user = User::factory()->create();

    SavedSearch::factory()->count(3)->create([
        'user_id' => $user->id,
        'is_active' => false,
    ]);

    Volt::actingAs($user)
        ->test('settings.notifications')
        ->call('resumeAllNotifications');

    expect(SavedSearch::where('user_id', $user->id)->where('is_active', false)->count())->toBe(0);
});

test('invalid notification channel is rejected', function (): void {
    $user = User::factory()->create();

    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'notification_channel' => 'email',
    ]);

    Volt::actingAs($user)
        ->test('settings.notifications')
        ->call('updateSearchChannel', $search->id, 'invalid');

    // Should remain unchanged
    $this->assertDatabaseHas('saved_searches', [
        'id' => $search->id,
        'notification_channel' => 'email',
    ]);
});

test('invalid notification frequency is rejected', function (): void {
    $user = User::factory()->create();

    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'notification_frequency' => 'daily',
    ]);

    Volt::actingAs($user)
        ->test('settings.notifications')
        ->call('updateSearchFrequency', $search->id, 'monthly');

    // Should remain unchanged
    $this->assertDatabaseHas('saved_searches', [
        'id' => $search->id,
        'notification_frequency' => 'daily',
    ]);
});
