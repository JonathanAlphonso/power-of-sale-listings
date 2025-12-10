<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AnalyticsSetting;
use App\Models\Listing;
use App\Models\User;
use App\Services\GoogleAnalytics\AnalyticsSummaryService;
use App\Support\MarketStatistics;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke(AnalyticsSummaryService $analyticsSummaryService): View
    {
        Gate::authorize('view-admin-dashboard');

        $totalListings = Listing::query()->count();
        $availableListings = Listing::query()
            ->where('display_status', 'Available')
            ->count();
        $averageListPrice = Listing::query()->avg('list_price');
        $recentListings = Listing::query()
            ->with(['municipality:id,name', 'source:id,name'])
            ->latest('modified_at')
            ->limit(5)
            ->get();
        $totalUsers = User::query()->count();
        $recentUsers = User::query()
            ->latest('created_at')
            ->limit(5)
            ->get(['id', 'name', 'email', 'created_at']);
        $analyticsSetting = AnalyticsSetting::current();
        $analyticsSummary = $analyticsSummaryService->summary($analyticsSetting);
        $dataFreshness = MarketStatistics::getDataFreshness();

        return view('dashboard', [
            'totalListings' => $totalListings,
            'availableListings' => $availableListings,
            'averageListPrice' => $averageListPrice,
            'recentListings' => $recentListings,
            'totalUsers' => $totalUsers,
            'recentUsers' => $recentUsers,
            'analyticsSetting' => $analyticsSetting,
            'analyticsSummary' => $analyticsSummary,
            'dataFreshness' => $dataFreshness,
        ]);
    }
}
