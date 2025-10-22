<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    $connected = false;
    $databaseName = null;
    $tableSample = [];
    $userCount = null;
    $errorMessage = null;

    try {
        $connection = DB::connection();
        $connection->getPdo();

        $connected = true;
        $databaseName = $connection->getDatabaseName();

        $tableSample = collect($connection->select('SHOW TABLES'))
            ->map(fn ($row) => collect($row)->first())
            ->take(5)
            ->all();

        if (Schema::hasTable((new User)->getTable())) {
            $userCount = User::count();
        }
    } catch (\Throwable $exception) {
        $connected = false;
        $errorMessage = $exception->getMessage();
    }

    return view('welcome', [
        'dbConnected' => $connected,
        'databaseName' => $databaseName,
        'tableSample' => $tableSample,
        'userCount' => $userCount,
        'dbErrorMessage' => $errorMessage,
    ]);
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

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
