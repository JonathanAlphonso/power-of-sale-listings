<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListingsController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', HomeController::class)->name('home');

Volt::route('listings', 'listings.index')->name('listings.index');

Route::get('listings/{listing}', [ListingsController::class, 'show'])->name('listings.show');

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
