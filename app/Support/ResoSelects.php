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
        // Keep this set minimal and portable across boards to avoid 400s for unknown fields.
        return implode(',', [
            'ListingKey',
            'ListingId',
            'OriginatingSystemName',
            'UnparsedAddress',
            'StreetNumber',
            'StreetName',
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
        // Select only fields the transformer and import actually use to reduce
        // chances of API errors on boards that omit certain fields.
        return implode(',', [
            'ListingKey',
            'OriginatingSystemName',
            'ListingId',
            'StandardStatus',
            'MlsStatus',
            'ContractStatus',
            'PropertyType',
            'PropertySubType',
            'StreetNumber',
            'StreetName',
            'UnitNumber',
            'City',
            'PostalCode',
            'StateOrProvince',
            'DaysOnMarket',
            'BedroomsTotal',
            'BathroomsTotalInteger',
            'ListPrice',
            'OriginalListPrice',
            'ModificationTimestamp',
            'ListingContractDate',
            'UnparsedAddress',
            'PublicRemarks',
            'TransactionType',
        ]);
    }
}
