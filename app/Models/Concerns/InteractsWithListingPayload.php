<?php

namespace App\Models\Concerns;

use App\Models\Listing;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

trait InteractsWithListingPayload
{
    use NormalizesListingValues;
    use ResolvesListingAssociations;
    use SyncsListingMedia;

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function upsertFromPayload(array $payload, array $context = []): Listing
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
}
