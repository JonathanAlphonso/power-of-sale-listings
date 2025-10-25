<?php

namespace App\Models\Concerns;

use App\Models\Municipality;
use App\Models\Source;
use Illuminate\Support\Str;

trait ResolvesListingAssociations
{
    /**
     * @param  array<string, mixed>  $context
     */
    protected static function resolveSourceId(array $payload, array $context): ?int
    {
        $source = $context['source'] ?? null;

        if ($source instanceof Source) {
            return $source->id;
        }

        if (isset($context['source_id'])) {
            return (int) $context['source_id'];
        }

        $identifier = $context['source_slug'] ?? $payload['gid'] ?? $payload['source'] ?? null;

        if (! is_string($identifier) || $identifier === '') {
            return null;
        }

        $slug = Str::slug($identifier);

        return Source::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $payload['sourceName'] ?? Str::headline($identifier),
                'type' => $payload['sourceType'] ?? $payload['class'] ?? null,
                'external_identifier' => $payload['gid'] ?? null,
            ],
        )->id;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected static function resolveMunicipalityId(array $payload, array $context): ?int
    {
        $municipality = $context['municipality'] ?? null;

        if ($municipality instanceof Municipality) {
            return $municipality->id;
        }

        if (isset($context['municipality_id'])) {
            return (int) $context['municipality_id'];
        }

        $city = $payload['city'] ?? $payload['district'] ?? null;

        if (! is_string($city) || $city === '') {
            return null;
        }

        $province = $payload['province'] ?? 'ON';
        $slug = Str::slug(sprintf('%s-%s', $province, $city));

        return Municipality::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $city,
                'province' => $province,
                'region' => $payload['region'] ?? null,
                'district' => $payload['district'] ?? null,
                'latitude' => self::floatOrNull($payload['latitude'] ?? null),
                'longitude' => self::floatOrNull($payload['longitude'] ?? null),
                'meta' => [
                    'source' => 'payload',
                ],
            ],
        )->id;
    }
}
