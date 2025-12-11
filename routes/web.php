<?php

use App\Http\Controllers\Api\MapListingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListingsController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', HomeController::class)->name('home');

Route::get('api/map-listings', MapListingsController::class)->name('api.map-listings');

Volt::route('listings', 'listings.index')->name('listings.index');

// New SEO-friendly URL format: /listings/{slug}/{id}
Route::get('listings/{slug}/{listing}', [ListingsController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->where('listing', '[0-9]+')
    ->name('listings.show');

// Backwards compatibility: redirect old /listings/{id} URLs to new format
Route::get('listings/{id}', function (string $id) {
    $listing = \App\Models\Listing::find($id);
    if (! $listing) {
        abort(404);
    }

    return redirect()->route('listings.show', ['slug' => $listing->slug, 'listing' => $listing->id], 301);
})->where('id', '[0-9]+');

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'admin'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::middleware(['admin'])->group(function () {
        Volt::route('admin/listings', 'admin.listings.index')
            ->name('admin.listings.index');

        Volt::route('admin/feeds', 'admin.feeds.index')
            ->name('admin.feeds.index');

        Volt::route('admin/listings/{listing}', 'admin.listings.show')
            ->name('admin.listings.show');

        Volt::route('admin/users', 'admin.users.index')
            ->name('admin.users.index');

        Volt::route('admin/settings/analytics', 'admin.settings.analytics')
            ->name('admin.settings.analytics');

        Volt::route('admin/settings/api-keys', 'admin.settings.api-keys')
            ->name('admin.settings.api-keys');
    });

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});

require __DIR__.'/auth.php';
