<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;

trait NormalizesListingValues
{
    protected static function floatOrNull(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $value = is_string($value)
            ? str_replace(['$', ',', ' '], '', $value)
            : $value;

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    protected static function intOrNull(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            $filtered = preg_replace('/[^\d\-]/', '', $value);

            if ($filtered === '') {
                return null;
            }

            return (int) $filtered;
        }

        return null;
    }

    protected static function carbonOrNull(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function extractFirstArrayValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $first = $value[0] ?? null;

            return is_string($first) ? $first : null;
        }

        return is_string($value) ? $value : null;
    }
}
