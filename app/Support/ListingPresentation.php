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
}
