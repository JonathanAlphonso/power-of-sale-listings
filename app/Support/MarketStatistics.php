<?php

namespace App\Support;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class MarketStatistics
{
    private const CACHE_TTL_SECONDS = 300; // 5 minutes

    /**
     * Get comprehensive market statistics with optional filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function getStatistics(array $filters = []): array
    {
        $cacheKey = 'market.stats.'.md5(serialize($filters));

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($filters) {
            $query = self::buildFilteredQuery($filters);

            $stats = $query->selectRaw('
                COUNT(*) as total_count,
                MIN(list_price) as min_price,
                MAX(list_price) as max_price,
                AVG(list_price) as avg_price,
                AVG(days_on_market) as avg_days_on_market
            ')->first();

            // Get median price (more accurate for real estate)
            $medianPrice = self::calculateMedianPrice(self::buildFilteredQuery($filters));

            // Get new listings counts
            $newThisWeek = self::buildFilteredQuery($filters)
                ->where('listed_at', '>=', Carbon::now()->subDays(7))
                ->count();

            $newToday = self::buildFilteredQuery($filters)
                ->where('listed_at', '>=', Carbon::today())
                ->count();

            // Get price change statistics
            $priceChanges = self::getPriceChangeStats(self::buildFilteredQuery($filters));

            return [
                'total' => (int) ($stats->total_count ?? 0),
                'min_price' => $stats->min_price ? (float) $stats->min_price : null,
                'max_price' => $stats->max_price ? (float) $stats->max_price : null,
                'avg_price' => $stats->avg_price ? (float) $stats->avg_price : null,
                'median_price' => $medianPrice,
                'avg_days_on_market' => $stats->avg_days_on_market ? round((float) $stats->avg_days_on_market) : null,
                'new_this_week' => $newThisWeek,
                'new_today' => $newToday,
                'price_reductions' => $priceChanges['reductions'],
                'price_increases' => $priceChanges['increases'],
                'avg_price_reduction_percent' => $priceChanges['avg_reduction_percent'],
            ];
        });
    }

    /**
     * Get week-over-week comparison statistics.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function getWeeklyTrends(array $filters = []): array
    {
        $cacheKey = 'market.trends.weekly.'.md5(serialize($filters));

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($filters) {
            $thisWeekStart = Carbon::now()->startOfWeek();
            $lastWeekStart = Carbon::now()->subWeek()->startOfWeek();
            $lastWeekEnd = $thisWeekStart->copy()->subSecond();

            // This week's stats
            $thisWeekQuery = self::buildFilteredQuery($filters)
                ->where('listed_at', '>=', $thisWeekStart);

            $thisWeekCount = $thisWeekQuery->count();
            $thisWeekAvgPrice = (clone $thisWeekQuery)->avg('list_price');

            // Last week's stats
            $lastWeekQuery = self::buildFilteredQuery($filters)
                ->whereBetween('listed_at', [$lastWeekStart, $lastWeekEnd]);

            $lastWeekCount = $lastWeekQuery->count();
            $lastWeekAvgPrice = (clone $lastWeekQuery)->avg('list_price');

            return [
                'this_week' => [
                    'count' => $thisWeekCount,
                    'avg_price' => $thisWeekAvgPrice ? (float) $thisWeekAvgPrice : null,
                ],
                'last_week' => [
                    'count' => $lastWeekCount,
                    'avg_price' => $lastWeekAvgPrice ? (float) $lastWeekAvgPrice : null,
                ],
                'count_change' => $thisWeekCount - $lastWeekCount,
                'count_change_percent' => $lastWeekCount > 0
                    ? round((($thisWeekCount - $lastWeekCount) / $lastWeekCount) * 100, 1)
                    : null,
                'price_change' => $thisWeekAvgPrice && $lastWeekAvgPrice
                    ? (float) $thisWeekAvgPrice - (float) $lastWeekAvgPrice
                    : null,
                'price_change_percent' => $thisWeekAvgPrice && $lastWeekAvgPrice && $lastWeekAvgPrice > 0
                    ? round(((float) $thisWeekAvgPrice - (float) $lastWeekAvgPrice) / (float) $lastWeekAvgPrice * 100, 1)
                    : null,
            ];
        });
    }

    /**
     * Get status breakdown statistics.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public static function getStatusBreakdown(array $filters = []): array
    {
        $cacheKey = 'market.status.breakdown.'.md5(serialize($filters));

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($filters) {
            return self::buildFilteredQuery($filters)
                ->selectRaw('display_status, COUNT(*) as count')
                ->groupBy('display_status')
                ->orderByDesc('count')
                ->pluck('count', 'display_status')
                ->toArray();
        });
    }

    /**
     * Get property type breakdown statistics.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public static function getPropertyTypeBreakdown(array $filters = []): array
    {
        $cacheKey = 'market.property_type.breakdown.'.md5(serialize($filters));

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($filters) {
            return self::buildFilteredQuery($filters)
                ->selectRaw('property_type, COUNT(*) as count')
                ->whereNotNull('property_type')
                ->groupBy('property_type')
                ->orderByDesc('count')
                ->pluck('count', 'property_type')
                ->toArray();
        });
    }

    /**
     * Get last sync/import timestamp.
     */
    public static function getLastSyncTimestamp(): ?Carbon
    {
        $cacheKey = 'market.last_sync';

        return Cache::remember($cacheKey, 60, function () {
            $lastModified = Listing::query()
                ->orderByDesc('updated_at')
                ->value('updated_at');

            return $lastModified ? Carbon::parse($lastModified) : null;
        });
    }

    /**
     * Get data freshness indicator.
     *
     * @return array{status: string, label: string, last_sync: ?string, minutes_ago: ?int}
     */
    public static function getDataFreshness(): array
    {
        $lastSync = self::getLastSyncTimestamp();

        if ($lastSync === null) {
            return [
                'status' => 'unknown',
                'label' => __('No data'),
                'last_sync' => null,
                'minutes_ago' => null,
            ];
        }

        $minutesAgo = $lastSync->diffInMinutes(Carbon::now());

        if ($minutesAgo <= 15) {
            $status = 'fresh';
            $label = __('Just updated');
        } elseif ($minutesAgo <= 60) {
            $status = 'recent';
            $label = __('Updated :minutes min ago', ['minutes' => $minutesAgo]);
        } elseif ($minutesAgo <= 1440) { // 24 hours
            $status = 'stale';
            $label = $lastSync->diffForHumans();
        } else {
            $status = 'outdated';
            $label = $lastSync->diffForHumans();
        }

        return [
            'status' => $status,
            'label' => $label,
            'last_sync' => $lastSync->toIso8601String(),
            'minutes_ago' => $minutesAgo,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Listing>
     */
    private static function buildFilteredQuery(array $filters): Builder
    {
        $query = Listing::query()->visible();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('mls_number', 'like', '%'.$search.'%')
                    ->orWhere('street_address', 'like', '%'.$search.'%')
                    ->orWhere('city', 'like', '%'.$search.'%')
                    ->orWhere('postal_code', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['status'])) {
            $query->where('display_status', $filters['status']);
        }

        if (! empty($filters['municipality_id'])) {
            $query->where('municipality_id', $filters['municipality_id']);
        }

        if (! empty($filters['property_type'])) {
            $query->where('property_type', $filters['property_type']);
        }

        if (! empty($filters['min_price'])) {
            $query->where('list_price', '>=', $filters['min_price']);
        }

        if (! empty($filters['max_price'])) {
            $query->where('list_price', '<=', $filters['max_price']);
        }

        if (! empty($filters['min_bedrooms'])) {
            $query->where('bedrooms', '>=', $filters['min_bedrooms']);
        }

        if (! empty($filters['min_bathrooms'])) {
            $query->where('bathrooms', '>=', $filters['min_bathrooms']);
        }

        return $query;
    }

    /**
     * @param  Builder<Listing>  $query
     */
    private static function calculateMedianPrice(Builder $query): ?float
    {
        $prices = $query->whereNotNull('list_price')
            ->where('list_price', '>', 0)
            ->orderBy('list_price')
            ->pluck('list_price');

        if ($prices->isEmpty()) {
            return null;
        }

        $count = $prices->count();
        $middle = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return (float) (($prices[$middle - 1] + $prices[$middle]) / 2);
        }

        return (float) $prices[$middle];
    }

    /**
     * @param  Builder<Listing>  $query
     * @return array{reductions: int, increases: int, avg_reduction_percent: ?float}
     */
    private static function getPriceChangeStats(Builder $query): array
    {
        $reductions = (clone $query)
            ->whereNotNull('original_list_price')
            ->whereNotNull('list_price')
            ->whereColumn('list_price', '<', 'original_list_price')
            ->count();

        $increases = (clone $query)
            ->whereNotNull('original_list_price')
            ->whereNotNull('list_price')
            ->whereColumn('list_price', '>', 'original_list_price')
            ->count();

        $avgReductionPercent = (clone $query)
            ->whereNotNull('original_list_price')
            ->whereNotNull('list_price')
            ->whereColumn('list_price', '<', 'original_list_price')
            ->where('original_list_price', '>', 0)
            ->selectRaw('AVG((original_list_price - list_price) / original_list_price * 100) as avg_reduction')
            ->value('avg_reduction');

        return [
            'reductions' => $reductions,
            'increases' => $increases,
            'avg_reduction_percent' => $avgReductionPercent ? round((float) $avgReductionPercent, 1) : null,
        ];
    }
}
