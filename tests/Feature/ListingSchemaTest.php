<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\ListingMedia;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

it('creates the listings table with expected columns', function (): void {
    expect(Schema::hasTable('listings'))->toBeTrue();

    expect(Schema::hasColumns('listings', [
        'id',
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
        'created_at',
        'updated_at',
        'deleted_at',
    ]))->toBeTrue();
});

it('creates the sources table with expected columns', function (): void {
    expect(Schema::hasTable('sources'))->toBeTrue();

    expect(Schema::hasColumns('sources', [
        'id',
        'slug',
        'name',
        'type',
        'external_identifier',
        'contact_name',
        'contact_email',
        'contact_phone',
        'website_url',
        'is_active',
        'last_synced_at',
        'config',
        'meta',
        'created_at',
        'updated_at',
        'deleted_at',
    ]))->toBeTrue();
});

it('creates the municipalities table with expected columns', function (): void {
    expect(Schema::hasTable('municipalities'))->toBeTrue();

    expect(Schema::hasColumns('municipalities', [
        'id',
        'slug',
        'name',
        'province',
        'region',
        'district',
        'latitude',
        'longitude',
        'meta',
        'created_at',
        'updated_at',
        'deleted_at',
    ]))->toBeTrue();
});

it('creates the listing status history table with expected columns', function (): void {
    expect(Schema::hasTable('listing_status_histories'))->toBeTrue();

    expect(Schema::hasColumns('listing_status_histories', [
        'id',
        'listing_id',
        'source_id',
        'status_code',
        'status_label',
        'notes',
        'changed_at',
        'payload',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

it('creates the saved searches table with expected columns', function (): void {
    expect(Schema::hasTable('saved_searches'))->toBeTrue();

    expect(Schema::hasColumns('saved_searches', [
        'id',
        'user_id',
        'name',
        'slug',
        'notification_channel',
        'notification_frequency',
        'is_active',
        'last_ran_at',
        'last_matched_at',
        'next_run_at',
        'filters',
        'meta',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

it('creates the audit logs table with expected columns', function (): void {
    expect(Schema::hasTable('audit_logs'))->toBeTrue();

    expect(Schema::hasColumns('audit_logs', [
        'id',
        'event_uuid',
        'action',
        'auditable_type',
        'auditable_id',
        'user_id',
        'old_values',
        'new_values',
        'meta',
        'ip_address',
        'user_agent',
        'occurred_at',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

it('persists listings with related media via factories', function (): void {
    $listing = Listing::factory()->create();

    ListingMedia::factory()
        ->count(3)
        ->sequence(
            ['position' => 0, 'is_primary' => true],
            ['position' => 1, 'is_primary' => false],
            ['position' => 2, 'is_primary' => false],
        )
        ->for($listing)
        ->create();

    $listing->load('media', 'source', 'municipality');

    expect($listing->media)->toHaveCount(3);
    expect($listing->media->firstWhere('is_primary', true))->not->toBeNull();
    expect($listing->source)->not->toBeNull();
    expect($listing->municipality)->not->toBeNull();

    assertDatabaseHas('listings', [
        'id' => $listing->id,
        'external_id' => $listing->external_id,
    ]);

    assertDatabaseHas('listing_media', [
        'listing_id' => $listing->id,
        'position' => 0,
        'is_primary' => true,
    ]);
});

it('maps payload data into canonical listing records', function (): void {
    $payload = [
        '_id' => 'TREB-W12449903',
        'gid' => 'TREB',
        'listingID' => 'W12449903',
        'displayStatus' => 'Available',
        'availability' => 'A',
        'class' => 'CONDO',
        'typeName' => 'Condo Townhouse',
        'style' => '2-Storey',
        'saleOrRent' => 'RENT',
        'currency' => 'CAD',
        'streetNumber' => '9',
        'streetName' => 'Lancewood',
        'streetAddress' => '9 Lancewood Cres',
        'city' => 'Brampton',
        'district' => 'Brampton',
        'neighborhoods' => 'Queen Street Corridor',
        'postalCode' => 'L6S 5Y6',
        'latitude' => 43.710025787353516,
        'longitude' => -79.73492431640625,
        'daysOnMarket' => 16,
        'bedrooms' => 3,
        'bedroomsPossible' => 1,
        'bathrooms' => 4,
        'squareFeet' => '1,400',
        'squareFeetText' => '1400-1599',
        'listPrice' => 3200,
        'originalListPrice' => 3200,
        'price' => 3200,
        'priceLow' => 3200,
        'priceChange' => 0,
        'priceChangeDirection' => 0,
        'displayAddressYN' => 'Y',
        'modified' => '2025-10-13T22:20:09.000Z',
        'imageSets' => [
            [
                'description' => 'Front exterior',
                'url' => 'https://example.test/full.jpg',
                'sizes' => [
                    '600' => 'https://example.test/600.jpg',
                    '900' => 'https://example.test/900.jpg',
                ],
            ],
        ],
    ];

    $listing = Listing::upsertFromPayload($payload);

    $listing->load('media', 'source', 'municipality', 'statusHistory');

    expect($listing->external_id)->toBe('TREB-W12449903');
    expect($listing->board_code)->toBe('TREB');
    expect($listing->mls_number)->toBe('W12449903');
    expect($listing->price)->toBe('3200.00');
    expect($listing->media)->toHaveCount(1);
    expect($listing->media->first()->url)->toBe('https://example.test/full.jpg');
    expect($listing->source?->slug)->toBe('treb');
    expect($listing->municipality?->name)->toBe('Brampton');
    expect($listing->statusHistory)->toHaveCount(1);

    $payload['price'] = 3100;
    $payload['imageSets'][] = [
        'description' => 'Rear yard',
        'url' => 'https://example.test/rear-full.jpg',
        'sizes' => [
            '600' => 'https://example.test/rear-600.jpg',
        ],
    ];

    $listing = Listing::upsertFromPayload($payload);

    $listing->load('media', 'statusHistory');

    expect($listing->price)->toBe('3100.00');
    expect($listing->media)->toHaveCount(2);
    expect($listing->media->firstWhere('is_primary', true)?->position)->toBe(0);
    expect($listing->payload['listingID'])->toBe('W12449903');
    expect($listing->statusHistory)->toHaveCount(1);

    assertDatabaseCount('listing_media', 2);
    assertDatabaseHas('sources', [
        'slug' => 'treb',
    ]);
    assertDatabaseHas('municipalities', [
        'slug' => 'on-brampton',
    ]);
});
