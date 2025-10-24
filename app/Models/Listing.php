<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Listing extends Model
{
    /** @use HasFactory<\Database\Factories\ListingFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'source_id',
        'municipality_id',
        'external_id',
        'board_code',
        'mls_number',
        'status_code',
        'display_status',
        'availability',
        'property_class',
        'property_type',
        'property_style',
        'sale_type',
        'currency',
        'street_number',
        'street_name',
        'street_address',
        'unit_number',
        'city',
        'district',
        'neighbourhood',
        'postal_code',
        'province',
        'latitude',
        'longitude',
        'days_on_market',
        'bedrooms',
        'bedrooms_possible',
        'bathrooms',
        'square_feet',
        'square_feet_text',
        'list_price',
        'original_list_price',
        'price',
        'price_low',
        'price_per_square_foot',
        'price_change',
        'price_change_direction',
        'is_address_public',
        'parcel_id',
        'modified_at',
        'payload',
        'ingestion_batch_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'bathrooms' => 'decimal:1',
        'is_address_public' => 'boolean',
        'latitude' => 'decimal:7',
        'list_price' => 'decimal:2',
        'longitude' => 'decimal:7',
        'modified_at' => 'datetime',
        'original_list_price' => 'decimal:2',
        'payload' => 'array',
        'price' => 'decimal:2',
        'price_low' => 'decimal:2',
        'price_per_square_foot' => 'decimal:2',
    ];

    public function media(): HasMany
    {
        return $this->hasMany(ListingMedia::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<Source, Listing>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * @return BelongsTo<Municipality, Listing>
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * @return HasMany<ListingStatusHistory>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(ListingStatusHistory::class)->orderByDesc('changed_at');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function upsertFromPayload(array $payload, array $context = []): self
    {
        $boardCode = $payload['gid'] ?? $payload['board'] ?? 'UNKNOWN';
        $mlsNumber = $payload['listingID'] ?? $payload['mls_number'] ?? $payload['mlsNumber'] ?? null;
        $externalId = $payload['_id'] ?? null;

        if ($externalId === null) {
            $externalId = trim(sprintf(
                '%s-%s',
                $boardCode,
                $mlsNumber ?? Str::uuid()->toString(),
            ), '-');
        }

        $mlsNumber ??= $externalId;

        $sourceId = self::resolveSourceId($payload, $context);
        $municipalityId = self::resolveMunicipalityId($payload, $context);

        $attributes = [
            'source_id' => $sourceId,
            'municipality_id' => $municipalityId,
            'board_code' => $boardCode,
            'mls_number' => $mlsNumber,
            'status_code' => $payload['displayStatus'] ?? $payload['availability'] ?? null,
            'display_status' => $payload['displayStatus'] ?? null,
            'availability' => $payload['availability'] ?? null,
            'property_class' => $payload['class'] ?? null,
            'property_type' => $payload['typeName'] ?? $payload['property_type'] ?? null,
            'property_style' => $payload['style'] ?? null,
            'sale_type' => isset($payload['saleOrRent']) ? strtoupper((string) $payload['saleOrRent']) : null,
            'currency' => $payload['currency'] ?? 'CAD',
            'street_number' => $payload['streetNumber'] ?? null,
            'street_name' => $payload['streetName'] ?? null,
            'street_address' => $payload['streetAddress'] ?? null,
            'unit_number' => Arr::get($payload, 'unit.number') ?? $payload['unitNumber'] ?? null,
            'city' => $payload['city'] ?? null,
            'district' => $payload['district'] ?? null,
            'neighbourhood' => $payload['neighborhoods'] ?? $payload['neighbourhood'] ?? null,
            'postal_code' => $payload['postalCode'] ?? null,
            'province' => $payload['province'] ?? 'ON',
            'latitude' => self::floatOrNull($payload['latitude'] ?? null),
            'longitude' => self::floatOrNull($payload['longitude'] ?? null),
            'days_on_market' => self::intOrNull($payload['daysOnMarket'] ?? null),
            'bedrooms' => self::intOrNull($payload['bedrooms'] ?? null),
            'bedrooms_possible' => self::intOrNull($payload['bedroomsPossible'] ?? null),
            'bathrooms' => self::floatOrNull($payload['bathrooms'] ?? null),
            'square_feet' => self::intOrNull($payload['squareFeet'] ?? null),
            'square_feet_text' => $payload['squareFeetText'] ?? null,
            'list_price' => self::floatOrNull($payload['listPrice'] ?? null),
            'original_list_price' => self::floatOrNull($payload['originalListPrice'] ?? null),
            'price' => self::floatOrNull($payload['price'] ?? null),
            'price_low' => self::floatOrNull($payload['priceLow'] ?? null),
            'price_per_square_foot' => self::floatOrNull($payload['pricePerSquareFoot'] ?? null),
            'price_change' => self::intOrNull($payload['priceChange'] ?? null),
            'price_change_direction' => self::intOrNull($payload['priceChangeDirection'] ?? null),
            'is_address_public' => isset($payload['displayAddressYN'])
                ? strtoupper((string) $payload['displayAddressYN']) === 'Y'
                : true,
            'parcel_id' => $payload['parcelID'] ?? null,
            'modified_at' => isset($payload['modified'])
                ? self::carbonOrNull((string) $payload['modified'])
                : null,
            'payload' => $payload,
            'ingestion_batch_id' => $context['ingestion_batch_id'] ?? null,
        ];

        $listing = static::query()->where('external_id', $externalId)->first();

        if ($listing === null) {
            $listing = static::query()->create(array_merge(
                ['external_id' => $externalId],
                $attributes,
            ));

            $listing->recordStatusHistory(
                $attributes['status_code'],
                $attributes['display_status'],
                $payload,
                $attributes['modified_at'] ?? now(),
            );
        } else {
            $listing->fill($attributes);

            $statusChanged = $listing->isDirty('status_code')
                || $listing->isDirty('display_status');

            $listing->save();

            if ($statusChanged) {
                $listing->recordStatusHistory(
                    $listing->status_code,
                    $listing->display_status,
                    $payload,
                    $listing->modified_at ?? now(),
                );
            }
        }

        $listing->syncMediaFromPayload(
            $payload['imageSets'] ?? [],
            $payload['images'] ?? [],
        );

        return $listing->fresh(['media', 'source', 'municipality']);
    }

    /**
     * @param  array<int|string, mixed>  $imageSets
     * @param  array<int, string>  $fallbackImages
     */
    private function syncMediaFromPayload(array $imageSets, array $fallbackImages): void
    {
        $this->media()->delete();

        if ($imageSets === []) {
            foreach ($fallbackImages as $index => $imageUrl) {
                $url = (string) $imageUrl;

                if ($url === '') {
                    continue;
                }

                $this->media()->create([
                    'media_type' => 'image',
                    'label' => null,
                    'position' => $index,
                    'is_primary' => $index === 0,
                    'url' => $url,
                    'preview_url' => $url,
                    'variants' => [],
                    'meta' => [
                        'source' => 'payload.images',
                    ],
                ]);
            }

            return;
        }

        foreach ($imageSets as $index => $imageSet) {
            $variants = Arr::wrap($imageSet['sizes'] ?? []);

            $primaryUrl = $imageSet['url'] ?? Arr::first($variants, static function ($value): bool {
                return is_string($value) && $value !== '';
            });

            if (! is_string($primaryUrl) || $primaryUrl === '') {
                $fallbackUrl = $fallbackImages[$index] ?? Arr::first($fallbackImages);

                if (! is_string($fallbackUrl) || $fallbackUrl === '') {
                    continue;
                }

                $primaryUrl = $fallbackUrl;
            }

            $this->media()->create([
                'media_type' => 'image',
                'label' => $imageSet['description'] ?? null,
                'position' => $index,
                'is_primary' => $index === 0,
                'url' => $primaryUrl,
                'preview_url' => $variants['900'] ?? $variants['600'] ?? $primaryUrl,
                'variants' => $variants,
                'meta' => array_filter([
                    'source' => 'payload.imageSets',
                    'description' => $imageSet['description'] ?? null,
                ]),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordStatusHistory(?string $statusCode, ?string $statusLabel, array $payload, Carbon|string|null $changedAt = null): void
    {
        if ($statusCode === null && $statusLabel === null) {
            return;
        }

        $timestamp = $changedAt instanceof Carbon
            ? $changedAt
            : ($changedAt !== null
                ? self::carbonOrNull((string) $changedAt) ?? now()
                : now());

        $this->statusHistory()->create([
            'source_id' => $this->source_id,
            'status_code' => $statusCode,
            'status_label' => $statusLabel,
            'changed_at' => $timestamp,
            'payload' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function resolveSourceId(array $payload, array $context): ?int
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
    private static function resolveMunicipalityId(array $payload, array $context): ?int
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

    private static function floatOrNull(mixed $value): ?float
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

    private static function intOrNull(mixed $value): ?int
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

    private static function carbonOrNull(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
