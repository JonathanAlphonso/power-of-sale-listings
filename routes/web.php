<?php

use App\Models\Listing;
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
    $sampleListings = collect();
    $listingsTableExists = false;

    try {
        $connection = DB::connection();
        $connection->getPdo();

        $connected = true;
        $databaseName = $connection->getDatabaseName();

        $tableSample = (match ($connection->getDriverName()) {
            'sqlite' => collect(
                $connection->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"),
            )->pluck('name'),
            'pgsql' => collect(
                $connection->select("SELECT tablename AS name FROM pg_tables WHERE schemaname = 'public'"),
            )->pluck('name'),
            'sqlsrv' => collect(
                $connection->select('SELECT TABLE_NAME AS name FROM INFORMATION_SCHEMA.TABLES'),
            )->pluck('name'),
            default => collect($connection->select('SHOW TABLES'))->map(fn ($row) => collect($row)->first()),
        })
            ->take(5)
            ->all();

        if (Schema::hasTable((new User)->getTable())) {
            $userCount = User::count();
        }

        if (Schema::hasTable((new Listing)->getTable())) {
            $listingsTableExists = true;
            $sampleListings = Listing::query()
                ->with(['source', 'municipality', 'media'])
                ->latest('modified_at')
                ->limit(3)
                ->get();
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
        'sampleListings' => $sampleListings,
        'listingsTableExists' => $listingsTableExists,
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
