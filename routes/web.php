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
                ->withoutRentals()
                ->visible()
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

Route::get('listings', function () {
    $listings = Listing::query()
        ->visible()
        ->with(['source:id,name', 'municipality:id,name', 'media'])
        ->latest('modified_at')
        ->paginate(12);

    return view('listings', [
        'listings' => $listings,
    ]);
})->name('listings.index');

Route::get('listings/{listing}', function (Listing $listing) {
    if ($listing->isSuppressed()) {
        abort(404);
    }

    $listing->load([
        'media' => fn ($query) => $query->orderBy('position'),
        'source:id,name',
        'municipality:id,name',
        'statusHistory' => fn ($query) => $query->orderByDesc('changed_at')->limit(5),
    ]);

    return view('listings.show', [
        'listing' => $listing,
    ]);
})->name('listings.show');

Route::get('dashboard', function () {
    $totalListings = Listing::query()
        ->withoutRentals()
        ->count();
    $availableListings = Listing::query()
        ->withoutRentals()
        ->where('display_status', 'Available')
        ->count();
    $averageListPrice = Listing::query()
        ->withoutRentals()
        ->avg('list_price');
    $recentListings = Listing::query()
        ->withoutRentals()
        ->with(['municipality:id,name', 'source:id,name'])
        ->latest('modified_at')
        ->limit(5)
        ->get();
    $totalUsers = User::query()->count();
    $recentUsers = User::query()
        ->latest('created_at')
        ->limit(5)
        ->get(['id', 'name', 'email', 'created_at']);

    return view('dashboard', [
        'totalListings' => $totalListings,
        'availableListings' => $availableListings,
        'averageListPrice' => $averageListPrice,
        'recentListings' => $recentListings,
        'totalUsers' => $totalUsers,
        'recentUsers' => $recentUsers,
    ]);
})
    ->middleware(['auth', 'verified', 'admin'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::middleware(['admin'])->group(function () {
        Volt::route('admin/listings', 'admin.listings.index')
            ->name('admin.listings.index');

        Volt::route('admin/listings/{listing}', 'admin.listings.show')
            ->name('admin.listings.show');

        Volt::route('admin/users', 'admin.users.index')
            ->name('admin.users.index');
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
