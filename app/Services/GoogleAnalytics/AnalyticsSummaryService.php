<?php

namespace App\Services\GoogleAnalytics;

use App\DataTransferObjects\AnalyticsSummary;
use App\Models\AnalyticsSetting;
use App\Services\GoogleAnalytics\Exceptions\AnalyticsException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;

class AnalyticsSummaryService
{
    private const CACHE_KEY = 'analytics:summary';

    private const CACHE_TTL_MINUTES = 15;

    /**
     * @var array<int, string>
     */
    private const METRIC_ORDER = [
        'totalUsers',
        'newUsers',
        'sessions',
        'engagementRate',
    ];

    public function __construct(
        private readonly AnalyticsClient $client,
        private readonly CacheRepository $cache
    ) {}

    public function summary(AnalyticsSetting $setting): AnalyticsSummary
    {
        $rangeEnd = CarbonImmutable::now()->startOfDay();
        $rangeStart = $rangeEnd->subDays(6);

        if (! $setting->isConfigured()) {
            return AnalyticsSummary::unavailable(
                rangeStart: $rangeStart,
                rangeEnd: $rangeEnd,
                refreshedAt: CarbonImmutable::now(),
                message: 'Connect Google Analytics to view engagement metrics.',
            );
        }

        $propertyId = is_string($setting->property_id) ? trim($setting->property_id) : '';
        $measurementId = is_string($setting->measurement_id) ? trim($setting->measurement_id) : '';
        $fingerprint = sha1($propertyId.'|'.$measurementId);
        $cacheKey = sprintf('%s:%s:%s', self::CACHE_KEY, (string) $setting->getKey(), $fingerprint);

        return $this->cache->remember(
            $cacheKey,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($setting, $rangeStart, $rangeEnd): AnalyticsSummary {
                try {
                    $report = $this->client->runReport(
                        $setting,
                        $rangeStart,
                        $rangeEnd,
                        self::METRIC_ORDER
                    );
                } catch (AnalyticsException $exception) {
                    Log::warning('Unable to load analytics summary.', [
                        'exception' => $exception,
                    ]);

                    return AnalyticsSummary::unavailable(
                        rangeStart: $rangeStart,
                        rangeEnd: $rangeEnd,
                        refreshedAt: CarbonImmutable::now(),
                        message: 'Analytics data is temporarily unavailable.',
                    );
                }

                if (! is_array($report)) {
                    return AnalyticsSummary::unavailable(
                        rangeStart: $rangeStart,
                        rangeEnd: $rangeEnd,
                        refreshedAt: CarbonImmutable::now(),
                        message: 'Analytics data is temporarily unavailable.',
                    );
                }

                $values = $report['rows'][0]['metricValues'] ?? [];

                $metrics = collect(self::METRIC_ORDER)
                    ->mapWithKeys(function (string $metric, int $index) use ($values): array {
                        $value = $values[$index]['value'] ?? null;

                        if ($value === null) {
                            return [$metric => null];
                        }

                        return [$metric => is_numeric($value) ? (float) $value : $value];
                    })
                    ->all();

                return AnalyticsSummary::make(
                    rangeStart: $rangeStart,
                    rangeEnd: $rangeEnd,
                    refreshedAt: CarbonImmutable::now(),
                    metrics: $metrics,
                    rangeLabel: 'Last 7 days',
                );
            }
        );
    }
}
