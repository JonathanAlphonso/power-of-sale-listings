<?php

namespace App\DataTransferObjects;

use Carbon\CarbonImmutable;

class AnalyticsSummary
{
    /**
     * @param  array<string, float|int|string>  $metrics
     */
    private function __construct(
        public readonly bool $configured,
        public readonly CarbonImmutable $rangeStart,
        public readonly CarbonImmutable $rangeEnd,
        public readonly CarbonImmutable $refreshedAt,
        public readonly array $metrics,
        public readonly ?string $message = null,
        public readonly string $rangeLabel = '',
    ) {}

    /**
     * @param  array<string, float|int|string>  $metrics
     */
    public static function make(
        CarbonImmutable $rangeStart,
        CarbonImmutable $rangeEnd,
        CarbonImmutable $refreshedAt,
        array $metrics,
        string $rangeLabel = '',
    ): self {
        return new self(
            configured: true,
            rangeStart: $rangeStart,
            rangeEnd: $rangeEnd,
            refreshedAt: $refreshedAt,
            metrics: $metrics,
            message: null,
            rangeLabel: $rangeLabel,
        );
    }

    public static function unavailable(
        CarbonImmutable $rangeStart,
        CarbonImmutable $rangeEnd,
        CarbonImmutable $refreshedAt,
        string $message
    ): self {
        return new self(
            configured: false,
            rangeStart: $rangeStart,
            rangeEnd: $rangeEnd,
            refreshedAt: $refreshedAt,
            metrics: [],
            message: $message,
            rangeLabel: '',
        );
    }
}
