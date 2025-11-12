# PropTx / IDX Data Mapping to Listings Schema

This document explains which PropTx (RESO OData) fields we request, how we query the API, and how those fields map to this app’s canonical `listings` schema. Use this when extending ingestion, writing tests, or optimizing queries.

## Endpoints & Auth

- Base URL: `IDX_BASE_URI` (e.g., `https://query.ampre.ca/odata/`)
- Auth: Bearer token `IDX_TOKEN` (PropTx-issued). Set in `.env`.
- Resources used:
  - `Property` for listing metadata.
  - `Media` for images (queried per listing key for a primary image).

## Queries We Run

- Default property fetch (homepage demo via `IdxClient`):
  - Path: `Property`
  - Filter: `StandardStatus eq 'Active'`
  - Order: prefer a stable, deterministic order:
    - `$orderby=ModificationTimestamp,ListingKey`
    - If recency is desired, use `$orderby=ModificationTimestamp desc,ListingKey`
  - Limit: `$top` set by the caller (e.g., 4)

- Optional “Power of Sale” discovery (reference only; not yet wired):
  - See `Docs/API Docs.txt` for an example `$filter` on `PublicRemarks` containing variations of “Power of Sale”.

- Media lookup (primary image by ListingKey):
  - Path: `Media`
  - Select: `MediaURL,MediaType,ResourceName,ResourceRecordKey,MediaModificationTimestamp`
  - Filter: `ResourceName eq 'Property' and ResourceRecordKey eq '{ListingKey}' and MediaCategory eq 'Photo' and MediaStatus eq 'Active'`
  - Order: `MediaModificationTimestamp desc`
  - Limit: `$top=1`

## Homepage Demo Feed

- The welcome page uses the Power of Sale query via `IdxClient::fetchPowerOfSaleListings(4)`.
- Results are cached for 5 minutes under the key `idx.pos.listings.{limit}` to reduce upstream load and page latency.
- Network timeouts:
  - Property requests: 6 seconds per call
  - Media requests: 3 seconds per listing
  - Failures return an empty feed and show a helpful banner; the page itself remains responsive.

## OData Limitations (PropTx)

PropTx's RESO OData implementation is intentionally limited. In practice, the following are not supported and should be avoided in queries and examples:

- `$expand` — no cross-entity joins; fetch related data with separate requests.
- `$batch` — no multi-request batching; iterate with `$top`/`$skip` and backoff.
- String functions like `tolower()`/`toupper()` — use `contains()` with explicit case variants or normalize after retrieval.

Guidance:

- Prefer simple `$filter` expressions using `eq`, `ne`, `and`, `or`, and `contains()`.
- Keep `$orderby` and `$top` straightforward; verify each new operator against the live API.
- When you need relationship data, perform subsequent requests keyed by `ListingKey` (as done for `Media`).

## Initial Seed Query (Power of Sale)

For an initial data seed covering properties that match our “Power of Sale” criteria, use this request. It selects only the fields we’ll use or store, filters on remarks and transaction type, and applies a deterministic order for paging.

```
GET /odata/Property
?$select=
  ListingKey,OriginatingSystemName,ListingId,StandardStatus,MlsStatus,ContractStatus,
  PropertyType,PropertySubType,ArchitecturalStyle,StreetNumber,StreetName,UnitNumber,City,
  CityRegion,PostalCode,StateOrProvince,DaysOnMarket,BedroomsTotal,BathroomsTotalInteger,
  LivingAreaRange,ListPrice,OriginalListPrice,ClosePrice,PreviousListPrice,PriceChangeTimestamp,
  ModificationTimestamp,UnparsedAddress,InternetAddressDisplayYN,ParcelNumber,PublicRemarks,
  TransactionType
&$filter=
  PublicRemarks ne null and (
    contains(PublicRemarks,'power of sale') or
    contains(PublicRemarks,'Power of Sale') or
    contains(PublicRemarks,'POWER OF SALE') or
    contains(PublicRemarks,'Power-of-Sale') or
    contains(PublicRemarks,'Power-of-sale') or
    contains(PublicRemarks,'P.O.S') or
    contains(PublicRemarks,' POS ') or
    contains(PublicRemarks,' POS,') or
    contains(PublicRemarks,' POS.') or
    contains(PublicRemarks,' POS-')
  )
  and TransactionType eq 'For Sale'
&$orderby=ModificationTimestamp,ListingKey
&$top=50
```

Notes

- Use `$skip` to paginate in increments of 50 while the `$orderby` remains stable.
- The repeated `contains()` calls cover case and punctuation variants; functions like `tolower()` are not supported.
- Tie-breaking by `ListingKey` ensures deterministic paging when timestamps match.

## Recommended $select (Efficiency)

Request only what we use. When implementing server-side ingestion, include a `$select` similar to:

```
$select=
  ListingKey,MLSNumber,OriginatingSystemName,
  UnparsedAddress,StreetNumber,StreetDirPrefix,StreetName,StreetSuffix,UnitNumber,
  City,StateOrProvince,PostalCode,
  StandardStatus,ModificationTimestamp,
  ListPrice,OriginalListPrice,
  PropertyType,PropertySubType,ArchitecturalStyle,
  BedroomsTotal,BathroomsTotalInteger,DaysOnMarket,
  PublicRemarks,ListOfficeName,VirtualTourURLBranded,VirtualTourURLUnbranded
```

You can add or remove fields as needed, but avoid requesting entire records without `$select`.

## Field Mapping → listings table

- Identity
  - PropTx `ListingKey` → `listings.external_id`
  - PropTx `MLSNumber` → `listings.mls_number`
  - PropTx `OriginatingSystemName` (or `SourceSystemName`) → `listings.board_code`

- Status & time
  - `StandardStatus` → `listings.display_status` (and/or `status_code`)
  - `ModificationTimestamp` → `listings.modified_at`
  - `ListingContractDate` (when provided) → `listings.listed_at`
  - `DaysOnMarket` is still ingested but primarily used to infer `listed_at` when the contract date is missing; UI days-on-market values are computed dynamically from `listed_at` so they remain accurate between syncs.

- Price
  - `ListPrice` → `listings.list_price`
  - `OriginalListPrice` → `listings.original_list_price`
  - Derived fields like `price_per_square_foot` can be computed later; raw values are preserved in `payload`.

- Address
  - `UnparsedAddress` → `listings.street_address`
  - `StreetNumber` → `listings.street_number`
  - `StreetName` (+ optional `StreetDirPrefix`, `StreetSuffix`) → `listings.street_name`
  - `UnitNumber` → `listings.unit_number`
  - `City` → `listings.city`
  - `StateOrProvince` → `listings.province`
  - `PostalCode` → `listings.postal_code`

- Property type & details
  - `PropertyType` → `listings.property_type`
  - `PropertySubType`/`ArchitecturalStyle` → `listings.property_style`
  - If available, `PropertyClass` → `listings.property_class`

- Size / beds / baths / DOM
  - `BedroomsTotal` → `listings.bedrooms`
  - `BathroomsTotalInteger` → `listings.bathrooms`
  - `BuildingAreaTotal` / `LivingAreaRange` → `listings.square_feet` / `listings.square_feet_text`
  - `DaysOnMarket` → `listings.days_on_market`

- Misc
  - `ParcelNumber` → `listings.parcel_id`
  - All other fields → `listings.payload` (JSON)

## Media Mapping → listing_media

- We query `Media` for each ListingKey to find the newest active photo.
- When ingesting to DB, we create `listing_media` rows with:
  - `media_type`: `image`
  - `label`: `null` (or media description if available)
  - `position`: `0` for primary; subsequent items increment
  - `url` + `preview_url`: best available URL(s)
  - `variants`: optional sizes map if provided
  - `meta.source`: `payload.imageSets` or `payload.images`

Note: For the homepage demo path, `IdxClient` attaches a single `image_url` to each card without writing to the DB.

## Status History

- When a listing’s `display_status`/`status_code` changes, we append a row to `listing_status_histories` with `changed_at` and the raw source payload. See `Listing::recordStatusHistory()`.

## Data Retention

- We always persist the upstream raw record in `listings.payload` for auditing and future enhancements. Only add dedicated columns when the data is actively used for filtering, sorting, or display.

## Do’s and Don’ts (for contributors/AI agents)

- Do: Favor `$select` and minimal filters to reduce payload size.
- Do: Map identity, status, pricing, address, type, and core stats to dedicated columns.
- Do: Store everything else in `payload`.
- Do: Use the `listing_status_histories` table to capture changes.
- Don’t: Introduce `PROPTX_*` env keys — `IDX_*` maps directly to PropTx credentials (see `AGENTS.md`).
