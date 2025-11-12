<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ListingDateResolver
{
    public static function parse(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function fromDaysOnMarket(int|float|string|null $value, ?CarbonInterface $reference = null): ?CarbonImmutable
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $days = (int) $value;

        if ($days < 0) {
            $days = 0;
        }

        $base = $reference !== null
            ? CarbonImmutable::instance($reference)
            : CarbonImmutable::now();

        return $base->subDays($days);
    }
}
