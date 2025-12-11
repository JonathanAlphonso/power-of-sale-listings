<?php

namespace App\Support;

class PropertyTypeAbbreviations
{
    private const ABBREVIATIONS = [
        // Condos
        'Condo Townhouse' => 'TC',
        'Condo Apt' => 'CA',
        'Comm Element Condo' => 'CE',

        // Townhouses
        'Att/Row/Twnhouse' => 'TH',
        'Townhouse' => 'TH',

        // Detached
        'Detached' => 'DET',
        'Link' => 'LNK',

        // Semi-Detached
        'Semi-Detached' => 'SD',

        // Multi-family
        'Duplex' => 'DUP',
        'Triplex' => 'TRI',
        'Fourplex' => '4PX',
        'Multiplex' => 'MPX',

        // Commercial
        'Commercial' => 'COM',
        'Industrial' => 'IND',
        'Store W/Apt/Office' => 'MIX',

        // Land
        'Vacant Land' => 'VL',
        'Farm' => 'FRM',

        // Other
        'Mobile/Trailer' => 'MOB',
        'Cottage' => 'COT',
    ];

    public static function get(?string $propertyType): string
    {
        if ($propertyType === null || $propertyType === '') {
            return 'P';
        }

        return self::ABBREVIATIONS[$propertyType] ?? 'P';
    }

    public static function all(): array
    {
        return self::ABBREVIATIONS;
    }
}
