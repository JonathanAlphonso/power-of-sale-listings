<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\User;
use App\Services\Idx\IdxClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function __invoke(IdxClient $idxClient): View
    {
        $connected = false;
        $databaseName = null;
        $tableSample = [];
        $userCount = null;
        $errorMessage = null;
        $sampleListings = collect();
        $listingsTableExists = false;
        $idxListings = collect();
        $idxFeedEnabled = $idxClient->isEnabled();

        try {
            $connection = DB::connection();
            $connection->getPdo();

            $connected = true;
            $databaseName = $connection->getDatabaseName();
            $driver = $connection->getDriverName();

            // Cache table listing for 60s to avoid repeated metadata scans.
            $tableSample = Cache::remember(sprintf('db.meta.tables.%s.%s', $driver, $databaseName), now()->addSeconds(60), function () use ($connection) {
                $tables = match ($connection->getDriverName()) {
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
                };

                return $tables->take(5)->all();
            });

            $hasUsersTable = Cache::remember(sprintf('db.meta.has_table.users.%s', $databaseName), now()->addSeconds(60), function (): bool {
                try {
                    return Schema::hasTable((new User)->getTable());
                } catch (\Throwable) {
                    return false;
                }
            });

            if ($hasUsersTable) {
                $userCount = User::count();
            }

            $hasListingsTable = Cache::remember(sprintf('db.meta.has_table.listings.%s', $databaseName), now()->addSeconds(60), function (): bool {
                try {
                    return Schema::hasTable((new Listing)->getTable());
                } catch (\Throwable) {
                    return false;
                }
            });

            if ($hasListingsTable) {
                $listingsTableExists = true;
                $sampleListings = Listing::query()
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

        if ($idxFeedEnabled) {
            try {
                $idxListings = collect($idxClient->fetchPowerOfSaleListings(4));

                if ($idxListings->isEmpty() && (bool) config('services.idx.homepage_fallback_to_active', true)) {
                    $fallback = collect($idxClient->fetchListings(4));
                    if ($fallback->isNotEmpty()) {
                        $idxListings = $fallback;
                    }
                }
            } catch (\Throwable $exception) {
                Log::warning('IDX listings failed to load for welcome page.', [
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return view('welcome', [
            'dbConnected' => $connected,
            'databaseName' => $databaseName,
            'tableSample' => $tableSample,
            'userCount' => $userCount,
            'dbErrorMessage' => $errorMessage,
            'sampleListings' => $sampleListings,
            'listingsTableExists' => $listingsTableExists,
            'idxListings' => $idxListings,
            'idxFeedEnabled' => $idxFeedEnabled,
        ]);
    }
}
