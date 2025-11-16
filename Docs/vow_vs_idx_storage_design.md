# VOW vs IDX Listing Storage Design

This document is instructions for the AI coding assistant.

You are working in **Laravel** with **MySQL**. The site consumes **both IDX and VOW feeds** for the *same* MLS listings.

Your job is to:

- Maintain **one canonical listing record per property**.
- Track **which feeds** (IDX/VOW) a listing was seen in.
- Optionally store **raw payloads** per source for debugging/auditing.

Do **not** create separate “IDX listings” and “VOW listings” tables that duplicate the whole schema.

---

## 1. Core principles

1. **Single canonical table**:  
   - All shared listing attributes (address, price, status, etc.) live in one `listings` table.
   - VOW-specific fields are extra columns on the same table (e.g. `sold_price`, `sold_date`, extra remarks, etc.).

2. **Source-awareness**:  
   - Track whether a given listing has been seen in IDX, VOW, or both.
   - Track the timestamps for the last update from each source.

3. **Raw payloads per source (optional but recommended)**:  
   - `raw_idx_listings`: persistent copy of the original IDX JSON for each listing + last processed timestamp.
   - `raw_vow_listings`: persistent copy of the original VOW JSON for each listing + last processed timestamp.

4. **Upsert, don’t duplicate**:  
   - Use `upsert` or `updateOrCreate` keyed on `external_id` + `board_code` (or `listing_key` + `board_code`) so the same listing from VOW and IDX merges into one row.

---

## 2. Database schema

### 2.1 Canonical `listings` table

Create or extend a `listings` table that represents the **superset** of fields you care about from both feeds.

> If a `listings` table already exists, **modify** it instead of recreating.

**Migration example:**

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();

            // Canonical identity
            $table->string('external_id', 64);     // e.g. ListingKey or provider-specific ID
            $table->string('board_code', 32);      // e.g. TRREB, ITSO, RAHB
            $table->string('mls_number', 64)->nullable();

            // Core public listing fields (common to IDX + VOW)
            $table->string('status_code', 32)->nullable();
            $table->string('display_status', 64)->nullable();
            $table->string('availability', 32)->nullable();
            $table->string('property_class', 32)->nullable();
            $table->string('property_type')->nullable();
            $table->string('property_style')->nullable();
            $table->string('sale_type', 32)->nullable(); // e.g. Normal, Power Of Sale, etc.

            $table->string('currency', 16)->default('CAD');
            $table->unsignedBigInteger('list_price')->nullable();
            $table->unsignedBigInteger('original_price')->nullable();

            // Location
            $table->string('street_address')->nullable();
            $table->string('city')->nullable();
            $table->string('province', 32)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();

            // IDX-visible remarks
            $table->text('public_remarks')->nullable();

            // VOW-only / enhanced fields
            $table->unsignedBigInteger('sold_price')->nullable();
            $table->dateTime('sold_date')->nullable();
            $table->text('vow_remarks')->nullable();        // longer or more detailed text
            $table->json('room_details')->nullable();       // room dimensions, etc.
            $table->json('history_events')->nullable();     // price changes, etc.

            // Source tracking
            $table->boolean('has_idx_data')->default(false);
            $table->boolean('has_vow_data')->default(false);
            $table->timestamp('last_seen_from_idx_at')->nullable();
            $table->timestamp('last_seen_from_vow_at')->nullable();

            // Replication timestamps from feed, if applicable
            $table->timestamp('feed_created_at')->nullable();      // Creation timestamp from feed
            $table->timestamp('feed_modified_at')->nullable();     // Last modification timestamp from feed

            // Maintenance
            $table->timestamps();

            $table->unique(['external_id', 'board_code'], 'listings_ext_board_unique');
            $table->index(['board_code', 'mls_number']);
            $table->index(['status_code', 'availability']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
```

Key points:

- `external_id` + `board_code` is the **canonical identity** for a listing.
- `has_idx_data`, `has_vow_data`, `last_seen_from_*` keep track of which feeds contributed.
- VOW-only fields are nullable; they are simply empty for listings only seen in IDX.

---

### 2.2 Raw payload tables

These tables store **unmodified JSON** from each feed for debugging, auditing, and potential reprocessing.

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('raw_idx_listings', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 64);
            $table->string('board_code', 32);
            $table->json('payload');                   // full raw IDX JSON
            $table->timestamp('fetched_at');           // when this payload was fetched
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['external_id', 'board_code'], 'raw_idx_ext_board_index');
        });

        Schema::create('raw_vow_listings', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 64);
            $table->string('board_code', 32);
            $table->json('payload');                   // full raw VOW JSON
            $table->timestamp('fetched_at');           // when this payload was fetched
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['external_id', 'board_code'], 'raw_vow_ext_board_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_idx_listings');
        Schema::dropIfExists('raw_vow_listings');
    }
};
```

These tables are **not** used directly by the frontend. They exist solely for:

- Diagnostics when the board changes something in the API.
- Re-running mapping logic without refetching from the MLS API.

---

### 2.3 Media (shared for both sources)

Media is shared regardless of VOW vs IDX; if both sources provide media, you should deduplicate by identity (e.g. `MediaKey`).

```php
Schema::create('listing_media', function (Blueprint $table) {
    $table->id();
    $table->foreignId('listing_id')->constrained()->cascadeOnDelete();

    $table->string('external_media_id', 64);  // e.g. MediaKey
    $table->string('media_type', 32)->nullable();   // Photo, Video, Floorplan
    $table->string('url');                          // URL from the feed
    $table->unsignedInteger('position')->default(0);

    $table->boolean('is_primary')->default(false);
    $table->timestamps();

    $table->unique(['listing_id', 'external_media_id'], 'listing_media_unique');
});
```

---

## 3. Eloquent models

### 3.1 `Listing` model

Create/update a `Listing` model that represents the canonical listing.

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Listing extends Model
{
    protected $fillable = [
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
        'list_price',
        'original_price',
        'street_address',
        'city',
        'province',
        'postal_code',
        'latitude',
        'longitude',
        'public_remarks',
        'sold_price',
        'sold_date',
        'vow_remarks',
        'room_details',
        'history_events',
        'has_idx_data',
        'has_vow_data',
        'last_seen_from_idx_at',
        'last_seen_from_vow_at',
        'feed_created_at',
        'feed_modified_at',
    ];

    protected $casts = [
        'sold_date'              => 'datetime',
        'room_details'           => 'array',
        'history_events'         => 'array',
        'has_idx_data'           => 'boolean',
        'has_vow_data'           => 'boolean',
        'last_seen_from_idx_at'  => 'datetime',
        'last_seen_from_vow_at'  => 'datetime',
        'feed_created_at'        => 'datetime',
        'feed_modified_at'       => 'datetime',
    ];

    public function media()
    {
        return $this->hasMany(ListingMedia::class);
    }

    /* Scopes for presentation */

    // Use this when building IDX (public) search/results
    public function scopeIdxVisible($query)
    {
        return $query
            ->where('has_idx_data', true);
        // Additional visibility filters can be added here if needed.
    }

    // Use this when building VOW (logged-in) search/results
    public function scopeVowVisible($query)
    {
        return $query
            ->where(function ($q) {
                $q->where('has_vow_data', true)
                  ->orWhere('has_idx_data', true);
            });
    }
}
```

---

## 4. Ingestion rules

### 4.1 IDX ingestion (job/command)

When processing an IDX feed batch:

1. Normalize the feed payload into a PHP array with fields matching the `listings` table.
2. Upsert into `listings` keyed by `external_id` + `board_code`.
3. **Never** erase VOW-only data when writing IDX data.
4. Set `has_idx_data = true` and `last_seen_from_idx_at = now()`.

Pseudocode for batch upsert:

```php
$now = now();

$rows = collect($idxPayloads)->map(function (array $item) use ($now) {
    return [
        'external_id'            => $item['ListingKey'],
        'board_code'             => $item['BoardCode'],
        'mls_number'             => $item['MLSNumber'] ?? null,
        'status_code'            => $item['Status'] ?? null,
        'display_status'         => $item['DisplayStatus'] ?? null,
        'availability'           => $item['Availability'] ?? null,
        'property_class'         => $item['PropertyClass'] ?? null,
        'property_type'          => $item['PropertyType'] ?? null,
        'property_style'         => $item['PropertyStyle'] ?? null,
        'sale_type'              => $item['SaleType'] ?? null,
        'currency'               => $item['Currency'] ?? 'CAD',
        'list_price'             => $item['ListPrice'] ?? null,
        'original_price'         => $item['OriginalPrice'] ?? null,
        'street_address'         => $item['StreetAddress'] ?? null,
        'city'                   => $item['City'] ?? null,
        'province'               => $item['Province'] ?? null,
        'postal_code'            => $item['PostalCode'] ?? null,
        'latitude'               => $item['Latitude'] ?? null,
        'longitude'              => $item['Longitude'] ?? null,
        'public_remarks'         => $item['PublicRemarks'] ?? null,
        'feed_created_at'        => $item['CreationTimestamp'] ?? null,
        'feed_modified_at'       => $item['ModificationTimestamp'] ?? null,
        'has_idx_data'           => true,
        'last_seen_from_idx_at'  => $now,
        'updated_at'             => $now,
        'created_at'             => $now,
    ];
})->all();

// Upsert by (external_id, board_code)
Listing::query()->upsert(
    $rows,
    ['external_id', 'board_code'],
    [
        'mls_number',
        'status_code',
        'display_status',
        'availability',
        'property_class',
        'property_type',
        'property_style',
        'sale_type',
        'currency',
        'list_price',
        'original_price',
        'street_address',
        'city',
        'province',
        'postal_code',
        'latitude',
        'longitude',
        'public_remarks',
        'feed_created_at',
        'feed_modified_at',
        'has_idx_data',
        'last_seen_from_idx_at',
        'updated_at',
    ]
);
```

If you store raw payloads, also insert/update `raw_idx_listings` with the raw JSON.

---

### 4.2 VOW ingestion (job/command)

When processing a VOW batch, follow the same pattern but include **VOW-only** fields:

1. Normalize feed payload to the canonical schema.
2. Upsert into `listings` with `external_id` + `board_code` key.
3. Do not overwrite existing IDX fields with `null` unless intentionally required.
4. Set `has_vow_data = true` and `last_seen_from_vow_at = now()`.

Pseudocode:

```php
$now = now();

$rows = collect($vowPayloads)->map(function (array $item) use ($now) {
    return [
        'external_id'            => $item['ListingKey'],
        'board_code'             => $item['BoardCode'],
        'mls_number'             => $item['MLSNumber'] ?? null,
        'status_code'            => $item['Status'] ?? null,
        'display_status'         => $item['DisplayStatus'] ?? null,
        'availability'           => $item['Availability'] ?? null,
        'property_class'         => $item['PropertyClass'] ?? null,
        'property_type'          => $item['PropertyType'] ?? null,
        'property_style'         => $item['PropertyStyle'] ?? null,
        'sale_type'              => $item['SaleType'] ?? null,
        'currency'               => $item['Currency'] ?? 'CAD',
        'list_price'             => $item['ListPrice'] ?? null,
        'original_price'         => $item['OriginalPrice'] ?? null,
        'street_address'         => $item['StreetAddress'] ?? null,
        'city'                   => $item['City'] ?? null,
        'province'               => $item['Province'] ?? null,
        'postal_code'            => $item['PostalCode'] ?? null,
        'latitude'               => $item['Latitude'] ?? null,
        'longitude'              => $item['Longitude'] ?? null,
        'public_remarks'         => $item['PublicRemarks'] ?? null,

        // VOW extras
        'vow_remarks'            => $item['VowRemarks'] ?? null,
        'sold_price'             => $item['SoldPrice'] ?? null,
        'sold_date'              => $item['SoldDate'] ?? null,
        'room_details'           => $item['RoomDetails'] ?? null,
        'history_events'         => $item['HistoryEvents'] ?? null,

        'feed_created_at'        => $item['CreationTimestamp'] ?? null,
        'feed_modified_at'       => $item['ModificationTimestamp'] ?? null,
        'has_vow_data'           => true,
        'last_seen_from_vow_at'  => $now,
        'updated_at'             => $now,
        'created_at'             => $now,
    ];
})->all();

Listing::query()->upsert(
    $rows,
    ['external_id', 'board_code'],
    [
        'mls_number',
        'status_code',
        'display_status',
        'availability',
        'property_class',
        'property_type',
        'property_style',
        'sale_type',
        'currency',
        'list_price',
        'original_price',
        'street_address',
        'city',
        'province',
        'postal_code',
        'latitude',
        'longitude',
        'public_remarks',
        'vow_remarks',
        'sold_price',
        'sold_date',
        'room_details',
        'history_events',
        'feed_created_at',
        'feed_modified_at',
        'has_vow_data',
        'last_seen_from_vow_at',
        'updated_at',
    ]
);
```

Again, optionally update `raw_vow_listings` with the raw JSON.

---

## 5. Presentation layer: IDX vs VOW

The **database** does not separate VOW vs IDX into different tables. Instead, use:

- Eloquent scopes (`idxVisible`, `vowVisible`).
- Different API Resources / transformers:
  - `ListingIdxResource` (fields safe for public/IDX).
  - `ListingVowResource` (fields allowed for authenticated VOW users).

Example idea for resources (no full code here):

- `ListingIdxResource`:
  - `list_price`, `street_address`, `city`, `public_remarks`, primary image, etc.
- `ListingVowResource`:
  - Everything from IDX + `sold_price`, `sold_date`, `room_details`, `history_events`, any VOW-only flags.

---

## 6. Summary of what you must do

1. Use **one canonical `listings` table** for both IDX and VOW data.
2. Add columns for:
   - VOW-only fields.
   - `has_idx_data`, `has_vow_data`, `last_seen_from_idx_at`, `last_seen_from_vow_at`.
3. Optionally create `raw_idx_listings` and `raw_vow_listings` to store **raw JSON payloads**.
4. For each feed:
   - Normalize data → canonical schema.
   - `upsert` into `listings` keyed by `external_id` + `board_code`.
   - Mark which source(s) the listing has data from.
5. Keep presentation logic separate:
   - Scopes and resources decide what fields show to IDX vs VOW users.

Implement everything in Laravel using migrations, models, and batch import jobs.
