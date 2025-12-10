<?php

namespace App\Support;

class ListingPresentation
{
    public static function statusBadge(?string $status): string
    {
        $normalized = strtolower((string) $status);

        return match (true) {
            str_contains($normalized, 'available') => 'green',
            str_contains($normalized, 'conditional') => 'amber',
            str_contains($normalized, 'sold') => 'red',
            str_contains($normalized, 'suspend') => 'zinc',
            default => 'blue',
        };
    }

    public static function currency(float|int|string|null $value): string
    {
        if ($value === null || $value === '') {
            return __('N/A');
        }

        return '$'.number_format((float) $value, 0);
    }

    public static function currencyShort(float|int|string|null $value): string
    {
        if ($value === null || $value === '') {
            return __('N/A');
        }

        $amount = (float) $value;

        if ($amount >= 1000000) {
            return '$'.number_format($amount / 1000000, 1).'M';
        }

        if ($amount >= 1000) {
            return '$'.number_format($amount / 1000, 0).'K';
        }

        return '$'.number_format($amount, 0);
    }

    public static function numeric(float|int|string|null $value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $number = (float) $value;

        if ($decimals === 0) {
            return number_format((int) round($number));
        }

        $formatted = number_format($number, $decimals);

        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * Calculate and format price per square foot.
     */
    public static function pricePerSqft(float|int|string|null $price, float|int|string|null $sqft): string
    {
        if ($price === null || $price === '' || $sqft === null || $sqft === '' || (float) $sqft <= 0) {
            return '—';
        }

        $pricePerSqft = (float) $price / (float) $sqft;

        return '$' . number_format($pricePerSqft, 0) . '/sqft';
    }

    /**
     * Get raw price per square foot value for comparison.
     */
    public static function pricePerSqftRaw(float|int|string|null $price, float|int|string|null $sqft): ?float
    {
        if ($price === null || $price === '' || $sqft === null || $sqft === '' || (float) $sqft <= 0) {
            return null;
        }

        return (float) $price / (float) $sqft;
    }
}
