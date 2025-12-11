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

    protected static function ensureArray(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            // Filter out empty values
            $filtered = array_filter($value, fn ($item) => $item !== null && $item !== '');

            return empty($filtered) ? null : array_values($filtered);
        }

        if (is_string($value) && $value !== '') {
            return [$value];
        }

        return null;
    }

    protected static function boolOrNull(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lower = strtolower($value);

            if (in_array($lower, ['y', 'yes', 'true', '1'], true)) {
                return true;
            }

            if (in_array($lower, ['n', 'no', 'false', '0'], true)) {
                return false;
            }
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        return null;
    }

    /**
     * Extract washroom details from payload.
     * The API provides up to 5 washrooms with type, level, and pieces.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array{type: string|null, level: string|null, pieces: int|null}>|null
     */
    protected static function extractWashrooms(array $payload): ?array
    {
        $washrooms = [];

        for ($i = 1; $i <= 5; $i++) {
            $type = $payload["WashroomsType{$i}"] ?? $payload["washroomsType{$i}"] ?? null;
            $level = $payload["WashroomsType{$i}Level"] ?? $payload["washroomsType{$i}Level"] ?? null;
            $pieces = $payload["WashroomsType{$i}Pcs"] ?? $payload["washroomsType{$i}Pcs"] ?? null;

            // Only add if we have at least one piece of data
            if ($type !== null || $level !== null || $pieces !== null) {
                $washrooms[] = [
                    'type' => $type,
                    'level' => $level,
                    'pieces' => $pieces !== null ? (int) $pieces : null,
                ];
            }
        }

        return empty($washrooms) ? null : $washrooms;
    }
}
