<?php

namespace App\Services\GoogleAnalytics;

use App\Models\AnalyticsSetting;
use App\Services\GoogleAnalytics\Exceptions\CredentialsException;
use App\Services\GoogleAnalytics\Exceptions\ReportException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;

class AnalyticsClient
{
    public function __construct(
        private readonly AccessTokenProvider $tokenProvider,
        private readonly HttpFactory $http
    ) {}

    /**
     * @param  array<int, string>  $metrics
     * @param  array<int, string>  $dimensions
     * @return array<string, mixed>|null
     */
    public function runReport(
        AnalyticsSetting $setting,
        CarbonImmutable $start,
        CarbonImmutable $end,
        array $metrics,
        array $dimensions = []
    ): ?array {
        if (! $setting->isConfigured()) {
            return null;
        }

        $credentials = $setting->service_account_credentials;

        if ($credentials instanceof \ArrayObject) {
            $credentials = $credentials->getArrayCopy();
        }

        if (! is_array($credentials)) {
            throw new CredentialsException('Analytics credentials are not available.');
        }

        $accessToken = $this->tokenProvider->retrieve($credentials);

        $endpoint = sprintf(
            'https://analyticsdata.googleapis.com/v1beta/properties/%s:runReport',
            $setting->property_id
        );

        $payload = [
            'dateRanges' => [
                [
                    'startDate' => $start->format('Y-m-d'),
                    'endDate' => $end->format('Y-m-d'),
                ],
            ],
            'metrics' => collect($metrics)
                ->filter()
                ->values()
                ->map(fn (string $metric): array => ['name' => $metric])
                ->all(),
            'dimensions' => collect($dimensions)
                ->filter()
                ->values()
                ->map(fn (string $dimension): array => ['name' => $dimension])
                ->all(),
        ];

        if (empty($payload['metrics'])) {
            throw new ReportException('At least one metric must be requested.');
        }

        $response = $this->http->withToken($accessToken)->post($endpoint, $payload);

        if (! $response->successful()) {
            throw new ReportException('The Google Analytics report request failed.');
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new ReportException('Unexpected Google Analytics response payload.');
        }

        $setting->markConnected();

        return $body;
    }
}
