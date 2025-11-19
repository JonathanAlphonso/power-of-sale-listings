<?php

declare(strict_types=1);

use App\Services\Idx\ListingTransformer;

it('transforms listing payload with sensible fallbacks', function (): void {
    /** @var ListingTransformer $transformer */
    $transformer = app(ListingTransformer::class);

    $now = now()->toIso8601String();

    $out = $transformer->transform([
        'ListingKey' => 'K1',
        'StreetNumber' => '1',
        'StreetName' => 'King',
        'StreetSuffix' => 'St W',
        'City' => 'Toronto',
        'StateOrProvince' => 'ON',
        'PostalCode' => 'M5H 1A1',
        'StandardStatus' => 'Active',
        'PropertyType' => 'Residential',
        'PropertySubType' => 'Detached',
        'PublicRemarks' => str_repeat('Nice. ', 60),
        'ModificationTimestamp' => $now,
    ]);

    expect($out['listing_key'])->toBe('K1');
    expect($out['address'])->toBe('1 King St W, Toronto, ON, M5H 1A1');
    expect($out['status'])->toBe('Active');
    expect($out['property_type'])->toBe('Residential');
    expect($out['property_sub_type'])->toBe('Detached');
    expect($out['remarks'])->toBeString()->toHaveLength(223)->toEndWith('...');
    expect($out['remarks_full'])->toBeString()->toHaveLength(strlen(trim(str_repeat('Nice. ', 60))));
    expect($out['modified_at'])->not->toBeNull();
});
