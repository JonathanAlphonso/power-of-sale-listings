<?php

declare(strict_types=1);

namespace App\Services\Idx;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ListingTransformer
{
    /**
     * @param  array<string, mixed>  $listing
     * @return array{
     *     listing_key: string|null,
     *     address: string|null,
     *     city: string|null,
     *     state: string|null,
     *     postal_code: string|null,
     *     list_price: float|int|string|null,
     *     status: string|null,
     *     property_type: string|null,
     *     property_sub_type: string|null,
     *     list_office_name: string|null,
     *     remarks: string|null,
     *     remarks_full: string|null,
     *     modified_at: CarbonImmutable|null,
     *     virtual_tour_url: string|null,
     *     image_url: string|null,
     * }
     */
    public function transform(array $listing): array
    {
        $remarks = Arr::get($listing, 'PublicRemarks');

        $standard = Arr::get($listing, 'StandardStatus');
        $mlsStatus = Arr::get($listing, 'MlsStatus');
        $contract = Arr::get($listing, 'ContractStatus');

        return [
            'listing_key' => Arr::get($listing, 'ListingKey'),
            'address' => Arr::get($listing, 'UnparsedAddress') ?? $this->buildAddress($listing),
            'city' => Arr::get($listing, 'City'),
            'state' => Arr::get($listing, 'StateOrProvince'),
            'postal_code' => Arr::get($listing, 'PostalCode'),
            'list_price' => Arr::get($listing, 'ListPrice'),
            'status' => is_string($standard) && $standard !== ''
                ? $standard
                : (is_string($mlsStatus) && $mlsStatus !== ''
                    ? $mlsStatus
                    : (is_string($contract) && $contract !== '' ? $contract : null)),
            'property_type' => Arr::get($listing, 'PropertyType'),
            'property_sub_type' => Arr::get($listing, 'PropertySubType'),
            'list_office_name' => Arr::get($listing, 'ListOfficeName'),
            'remarks' => is_string($remarks) ? Str::limit(trim($remarks), 220) : null,
            'remarks_full' => is_string($remarks) ? trim($remarks) : null,
            'modified_at' => CarbonImmutable::make(Arr::get($listing, 'ModificationTimestamp')),
            'virtual_tour_url' => Arr::get($listing, 'VirtualTourURLBranded') ?? Arr::get($listing, 'VirtualTourURLUnbranded'),
            'image_url' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $listing
     */
    private function buildAddress(array $listing): ?string
    {
        $street = array_filter([
            Arr::get($listing, 'StreetNumber'),
            Arr::get($listing, 'StreetDirPrefix'),
            Arr::get($listing, 'StreetName'),
            Arr::get($listing, 'StreetSuffix'),
        ]);

        if ($street === []) {
            return null;
        }

        $cityLine = array_filter([
            Arr::get($listing, 'City'),
            Arr::get($listing, 'StateOrProvince'),
            Arr::get($listing, 'PostalCode'),
        ]);

        $address = implode(' ', $street);

        if ($cityLine !== []) {
            $address .= ', '.implode(', ', $cityLine);
        }

        return $address;
    }
}
