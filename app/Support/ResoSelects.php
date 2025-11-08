<?php

declare(strict_types=1);

namespace App\Support;

class ResoSelects
{
    public static function propertyCard(): string
    {
        return implode(',', [
            'ListingKey',
            'ListingId',
            'OriginatingSystemName',
            'UnparsedAddress',
            'StreetNumber',
            'StreetDirPrefix',
            'StreetName',
            'StreetSuffix',
            'UnitNumber',
            'City',
            'StateOrProvince',
            'PostalCode',
            'StandardStatus',
            'ModificationTimestamp',
            'ListPrice',
            'OriginalListPrice',
            'PropertyType',
            'PropertySubType',
            'ListOfficeName',
            'PublicRemarks',
            'VirtualTourURLBranded',
            'VirtualTourURLUnbranded',
        ]);
    }

    public static function propertyPowerOfSaleCard(): string
    {
        return implode(',', [
            'ListingKey',
            'ListingId',
            'OriginatingSystemName',
            'UnparsedAddress',
            'StreetNumber',
            'StreetDirPrefix',
            'StreetName',
            'StreetSuffix',
            'UnitNumber',
            'City',
            'StateOrProvince',
            'PostalCode',
            'StandardStatus',
            'MlsStatus',
            'ContractStatus',
            'ModificationTimestamp',
            'ListPrice',
            'OriginalListPrice',
            'PropertyType',
            'PropertySubType',
            'ListOfficeName',
            'PublicRemarks',
            'VirtualTourURLBranded',
            'VirtualTourURLUnbranded',
            'TransactionType',
        ]);
    }

    public static function propertyPowerOfSaleImport(): string
    {
        return implode(',', [
            'ListingKey', 'OriginatingSystemName', 'ListingId', 'StandardStatus', 'MlsStatus', 'ContractStatus',
            'PropertyType', 'PropertySubType', 'ArchitecturalStyle', 'StreetNumber', 'StreetName', 'UnitNumber', 'City',
            'CityRegion', 'PostalCode', 'StateOrProvince', 'DaysOnMarket', 'BedroomsTotal', 'BathroomsTotalInteger',
            'LivingAreaRange', 'ListPrice', 'OriginalListPrice', 'ClosePrice', 'PreviousListPrice', 'PriceChangeTimestamp',
            'ModificationTimestamp', 'UnparsedAddress', 'InternetAddressDisplayYN', 'ParcelNumber', 'PublicRemarks',
            'TransactionType',
        ]);
    }
}
