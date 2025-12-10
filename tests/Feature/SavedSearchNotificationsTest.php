<?php

use App\Jobs\ProcessSavedSearchNotifications;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\NewListingsMatchedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Notification::fake();
});

test('notification job finds matching listings for saved search', function (): void {
    $user = User::factory()->create();

    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
        'notification_channel' => 'email',
        'notification_frequency' => 'instant',
        'filters' => ['type' => 'Condo'],
        'last_ran_at' => now()->subHour(),
    ]);

    // Create matching listings after last_ran_at
    Listing::factory()->count(3)->create([
        'property_type' => 'Condo',
        'created_at' => now(),
    ]);

    // Create non-matching listings
    Listing::factory()->count(2)->create([
        'property_type' => 'House',
        'created_at' => now(),
    ]);

    (new ProcessSavedSearchNotifications('instant'))->handle();

    Notification::assertSentTo(
        $user,
        NewListingsMatchedNotification::class,
        function ($notification) {
            return $notification->listings->count() === 3;
        }
    );
});

test('notification job respects active status', function (): void {
    $user = User::factory()->create();

    SavedSearch::factory()->create([
        'user_id' => $user->id,
        'is_active' => false,
        'notification_channel' => 'email',
        'notification_frequency' => 'instant',
    ]);

    Listing::factory()->create(['created_at' => now()]);

    (new ProcessSavedSearchNotifications('instant'))->handle();

    Notification::assertNothingSent();
});

test('notification job respects notification channel', function (): void {
    $user = User::factory()->create();

    SavedSearch::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
        'notification_channel' => 'none',
        'notification_frequency' => 'instant',
    ]);

    Listing::factory()->create(['created_at' => now()]);

    (new ProcessSavedSearchNotifications('instant'))->handle();

    Notification::assertNothingSent();
});

test('notification job respects frequency filter', function (): void {
    $user = User::factory()->create();

    SavedSearch::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
        'notification_channel' => 'email',
        'notification_frequency' => 'daily',
    ]);

    Listing::factory()->create(['created_at' => now()]);

    // Running instant job should not process daily searches
    (new ProcessSavedSearchNotifications('instant'))->handle();

    Notification::assertNothingSent();
});

test('notification job updates last ran and matched timestamps', function (): void {
    $user = User::factory()->create();

    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
        'notification_channel' => 'email',
        'notification_frequency' => 'instant',
        'filters' => [], // No filters - match all listings
        'last_ran_at' => null,
        'last_matched_at' => null,
    ]);

    Listing::factory()->create(['created_at' => now()]);

    (new ProcessSavedSearchNotifications('instant'))->handle();

    $search->refresh();

    expect($search->last_ran_at)->not->toBeNull();
    expect($search->last_matched_at)->not->toBeNull();
});

test('notification job applies price filters', function (): void {
    $user = User::factory()->create();

    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
        'notification_channel' => 'email',
        'notification_frequency' => 'instant',
        'filters' => [
            'min_price' => '200000',
            'max_price' => '500000',
        ],
        'last_ran_at' => now()->subHour(),
    ]);

    // Create matching listing
    Listing::factory()->create([
        'list_price' => 350000,
        'created_at' => now(),
    ]);

    // Create listings outside price range
    Listing::factory()->create([
        'list_price' => 100000,
        'created_at' => now(),
    ]);

    Listing::factory()->create([
        'list_price' => 800000,
        'created_at' => now(),
    ]);

    (new ProcessSavedSearchNotifications('instant'))->handle();

    Notification::assertSentTo(
        $user,
        NewListingsMatchedNotification::class,
        function ($notification) {
            return $notification->listings->count() === 1
                && $notification->listings->first()->list_price == 350000;
        }
    );
});

test('notification job applies bedroom and bathroom filters', function (): void {
    $user = User::factory()->create();

    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
        'notification_channel' => 'email',
        'notification_frequency' => 'instant',
        'filters' => [
            'beds' => '3',
            'baths' => '2',
        ],
        'last_ran_at' => now()->subHour(),
    ]);

    // Create matching listing
    Listing::factory()->create([
        'bedrooms' => 4,
        'bathrooms' => 2.5,
        'created_at' => now(),
    ]);

    // Create non-matching listings
    Listing::factory()->create([
        'bedrooms' => 2,
        'bathrooms' => 1,
        'created_at' => now(),
    ]);

    (new ProcessSavedSearchNotifications('instant'))->handle();

    Notification::assertSentTo(
        $user,
        NewListingsMatchedNotification::class,
        function ($notification) {
            return $notification->listings->count() === 1;
        }
    );
});

test('notification does not send when no new listings match', function (): void {
    $user = User::factory()->create();

    $search = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
        'notification_channel' => 'email',
        'notification_frequency' => 'instant',
        'last_ran_at' => now()->subHour(),
    ]);

    // Create listing before last_ran_at
    Listing::factory()->create([
        'created_at' => now()->subDays(2),
    ]);

    (new ProcessSavedSearchNotifications('instant'))->handle();

    Notification::assertNothingSent();
});

test('notification job is scheduled', function (): void {
    $scheduledJobs = app(\Illuminate\Console\Scheduling\Schedule::class)->events();

    $instantJobScheduled = collect($scheduledJobs)->contains(function ($event) {
        return str_contains($event->description ?? '', 'instant saved search');
    });

    $dailyJobScheduled = collect($scheduledJobs)->contains(function ($event) {
        return str_contains($event->description ?? '', 'daily saved search');
    });

    $weeklyJobScheduled = collect($scheduledJobs)->contains(function ($event) {
        return str_contains($event->description ?? '', 'weekly saved search');
    });

    expect($instantJobScheduled)->toBeTrue();
    expect($dailyJobScheduled)->toBeTrue();
    expect($weeklyJobScheduled)->toBeTrue();
});
