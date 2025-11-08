# Power of Sale Ontario DB — Codebase Deep Dive

This document gives a practical, file-by-file tour of the application so a new contributor can understand what exists, why it exists, and how it fits together. It follows the repository structure and calls out key implementation details, conventions, and flows.

If you are onboarding, read this in order once, then jump to the sections you are touching.


## Architecture Overview

- Laravel 12 + PHP 8.3: Streamlined bootstrap in `bootstrap/app.php` with routing/middleware configured there. No `App\\Http\\Kernel`.
- Livewire v3 + Volt: Server-driven UI. Volt single-file components live in `resources/views/livewire/**`. Layouts under `resources/views/components/layouts/*`.
- Flux UI Free: Blade-based components (e.g. `<flux:button>`) used across pages.
- Tailwind CSS v4: Imported via `@import "tailwindcss";` in `resources/js/app.js` and `resources/css/app.css`.
- Data model: Canonical `listings` + related tables (`listing_media`, `listing_status_history`, `listing_suppressions`, `sources`, `municipalities`, etc.).
- Ingestion: IDX/VOW via PropTx RESO OData API. See `app/Services/Idx/**` and ingestion jobs in `app/Jobs/**`.
- AuthN/AuthZ: Fortify v1, custom gates and policies, admin area behind `admin` middleware alias.
- Analytics: Optional GA4 with service account for dashboard metrics and optional client snippet.
- Queues: Database queue by default (see `.env.example`), jobs dispatch to named queues (e.g. `media`).


## Entry Points & Bootstrapping

- `bootstrap/app.php`
  - Registers web routes (`routes/web.php`), console commands (`routes/console.php`) and the health endpoint.
  - Aliases middleware `admin` to `App\\Http\\Middleware\\EnsureUserIsAdmin`.
  - Use this to wire global middleware/exception config.

- `bootstrap/providers.php`
  - Application service providers auto-loaded by Laravel 12:
    - `AppServiceProvider` – general hooks.
    - `AuthServiceProvider` – gates and policy mappings.
    - `FortifyServiceProvider` – auth customization.
    - `VoltServiceProvider` – Livewire Volt view mounting.

- `artisan`
  - Standard Laravel entry for CLI commands (`php artisan`). Commands in `app/Console/Commands` auto-register in Laravel 12.


## Routing

- `routes/web.php`
  - Public routes: `GET /` → `HomeController` (welcome/demo page), `GET /listings` and `GET /listings/{listing}` for public listing browsing.
  - Admin-only: `GET /dashboard` → `DashboardController` with `auth`, `verified`, `admin` middleware.
  - Volt routes inside `Route::middleware(['auth'])`: settings pages and admin workspace (admin listings, feeds, users, settings/analytics).

- `routes/auth.php`
  - Guest Volt routes: login, register, forgot/reset password.
  - Authenticated routes: verify email flow (`verify-email` screen and signed `verify-email/{id}/{hash}` handled by `Auth\\VerifyEmailController`).
  - POST `logout` handled by `App\\Livewire\\Actions\\Logout` action.

- `routes/console.php`
  - Example `inspire` command. Add command closures here, or create class-based commands under `app/Console/Commands`.


## Middleware

- `app/Http/Middleware/EnsureUserIsAdmin.php`
  - Protects admin area. Enforces:
    - Must be authenticated `User` and not suspended.
    - Promotes first user to admin if no active admins exist (bootstrap scenario).
    - Authorizes via `Gate::authorize('access-admin-area')`.


## Controllers

- `app/Http/Controllers/HomeController.php`
  - Welcome page. Probes DB connectivity (driver, sample tables, user counts), samples latest listings when the `listings` table exists, fetches live IDX Power of Sale (PoS) demo cards via `IdxClient` with a short cache window.

- `app/Http/Controllers/ListingsController.php`
  - `index()`: Public listings grid. Scope `visible()`, eager-load `source`, `municipality`, and `media`, sort by `modified_at`, paginate 12.
  - `show(Listing $listing)`: Detail page. 404 when suppressed; eager-load media (ordered), associations, and the 5 latest status changes.

- `app/Http/Controllers/DashboardController.php`
  - Admin dashboard. Gate `view-admin-dashboard` enforced.
  - Aggregates counts (listings, available, average price, latest listings, users) and GA4 summary via `AnalyticsSummaryService`.

- `app/Http/Controllers/Auth/VerifyEmailController.php`
  - Completes email verification; redirects to `dashboard` with `?verified=1`.


## Policies & Gates

- `app/Providers/AuthServiceProvider.php`
  - Maps `Listing` → `ListingPolicy`, `User` → `UserPolicy`.
  - Gates: `access-admin-area`, `view-admin-dashboard`, `manage-analytics-settings` restricted to not-suspended admins.

- `app/Policies/**`
  - `Concerns/AuthorizesAdmins`: blocks suspended users early; utility `allowsAdmin()`.
  - `ListingPolicy`: administer, suppress/unsuppress, purge, seed restricted to admins.
  - `UserPolicy`: manage other users; prevents self-delete/suspend.


## Authentication (Fortify) and Session Flows

- `app/Providers/FortifyServiceProvider.php`
  - Binds custom `LoginResponse` and overrides `authenticateUsing` to block suspended users and show helpful copy for forced password rotations.
  - Adds a rate limiter for two-factor.

- `app/Livewire/Actions/Logout.php`
  - Stateless action that logs out, clears session, regenerates token, and redirects home.

- `app/Livewire/Support/ManagesTwoFactor.php`
  - Trait used by the Volt `settings/two-factor` component. Orchestrates enabling/confirming/disabling TOTP, exposes modal state and validation.

- `app/Enums/UserRole.php`
  - Role enum with helpers and labels. Admin/subscriber checks are used widely (e.g., middleware, gates, redirects).


## Models (Data Layer)

- `app/Models/Listing.php`
  - Canonical record. Fillables include address, price, metrics, suppression fields, and raw `payload` (array cast). Soft-deletes enabled.
  - Relations: `media()`, `source()`, `municipality()`, `statusHistory()`, `suppressions()`, `currentSuppression()`, `suppressedBy()`.
  - Scopes: `visible()` hides currently suppressed items; `suppressed()` returns only active suppressions. Defensive `suppressionSchemaAvailable()` guards for early-migration environments.
  - Integrates traits below for ingestion, normalization, and media sync.

- `app/Models/Concerns/InteractsWithListingPayload.php`
  - High-level ingestion entrypoint: `Listing::upsertFromPayload($payload, $context)` normalizes, assigns associations (source/municipality), writes status history, and syncs media placeholders.

- `app/Models/Concerns/NormalizesListingValues.php`
  - Helpers to safely coerce payload values to float/int/Carbon while filtering symbols (e.g., `$`, `,`).

- `app/Models/Concerns/ResolvesListingAssociations.php`
  - Resolves or creates `Source`/`Municipality` records from payload/context using slugs.

- `app/Models/Concerns/SyncsListingMedia.php`
  - Replaces media set based on `imageSets` + `images` payload; queues `DownloadListingMedia` when enabled.

- `app/Models/ListingMedia.php`
  - Holds media URLs, optional stored location (`stored_disk`, `stored_path`, `stored_at`), computed `public_url` returning stored URL or remote fallback.

- `app/Models/ListingStatusHistory.php`
  - Records status transitions with an optional `source_id` and raw payload snapshot.

- `app/Models/ListingSuppression.php`
  - Tracks suppressions; `isActive()` reflects expiry; relations to listing and (release) users.

- `app/Models/Source.php`, `app/Models/Municipality.php`
  - Dimensions with soft-deletes and basic lifecycle hooks (slugging), plus `hasMany(Listing)`.

- `app/Models/AnalyticsSetting.php`
  - Feature-flag style settings for GA4: client snippet vs server metrics, encrypted credentials, helper accessors, and `current()` singleton.

- `app/Models/AuditLog.php`
  - Generic audit trail with polymorphic `auditable()` and automatic UUID/timestamping.

- `app/Models/SavedSearch.php`
  - User-scoped saved filters (reserved for future features); slugging and typed casts.

- `app/Models/User.php`
  - Fortify user with role casts, admin/subscriber helpers, `savedSearches`, and protection flags (suspended, forced password rotation).


## Services — IDX (PropTx) Integration

- `app/Services/Idx/IdxClient.php`
  - HTTP client for PropTx RESO OData. Uses `services.idx.base_uri` and `services.idx.token`.
  - `isEnabled()` guards on configured credentials.
  - `fetchListings($limit)` returns recent Active listings; applies deterministic sort and defensive filtering; hydrates a primary image via pooled Media lookups.
  - `fetchPowerOfSaleListings($limit)` uses `ResoFilters::powerOfSale()` to find PoS by remark text. Five-minute cache on both endpoints.
  - `fetchPropertyMedia($listingKey, $limit)` fetches photo media for a Property, filtered to a consistent size; used by background media sync.
  - Light-weight metrics written to cache for request counts/statuses.

- `app/Services/Idx/ListingTransformer.php`
  - Normalizes RESO `Property` records to our compact homepage card array: address lines, pricing, statuses (prefers StandardStatus, then MLS/Contract), limited remarks, and virtual tour.

- `app/Services/Idx/RequestFactory.php`
  - Builds tuned `PendingRequest` instances for IDX or VOW with base headers and optional `Prefer: odata.maxpagesize`.

- `app/Support/ResoSelects.php`, `app/Support/ResoFilters.php`
  - Centralized `$select` strings and PoS filter, so HTTP methods stay readable and consistent across services/jobs.

- `app/Support/BoardCode.php`
  - Derives a compact board code from an originating system name (acronym-like), with sensible fallbacks.


## Services — Google Analytics (optional)

- `app/Services/GoogleAnalytics/AccessTokenProvider.php`
  - Creates a signed JWT assertion with the GA4 service account to obtain an OAuth token; caches for ~55 minutes.

- `app/Services/GoogleAnalytics/AnalyticsClient.php`
  - Calls GA4 `runReport` for configured property, returns raw response, marks the setting as connected on success.

- `app/Services/GoogleAnalytics/AnalyticsSummaryService.php`
  - Produces a `DataTransferObjects/AnalyticsSummary` for a 7-day range, cached for 15 minutes. Surfaces friendly messages when not configured.

- `app/DataTransferObjects/AnalyticsSummary.php`
  - Minimal immutable DTO for dashboard display.


## Jobs & Queues

- `app/Jobs/ImportIdxPowerOfSale.php`
  - Paginates PoS via IDX using `$top/$skip` and `@odata.nextLink`. Upserts listings through `ListingTransformer` into canonical `listings`, writes status history, and enqueues media sync. Uses `WithoutOverlapping` to avoid duplicate runs; progress written to cache.

- `app/Jobs/ImportVowPowerOfSale.php`
  - Same strategy for the VOW feed (private feed). Applies source priority so an existing IDX-sourced listing isn’t downgraded by VOW.

- `app/Jobs/ImportAllPowerOfSaleFeeds.php`
  - Serially runs IDX then VOW imports with a single lock (safest end-to-end trigger).

- `app/Jobs/SyncIdxMediaForListing.php`
  - Fetches `Media` items for a ListingKey and replaces `listing_media` rows, optionally queueing `DownloadListingMedia` when configured.

- `app/Jobs/DownloadListingMedia.php`
  - Downloads remote image to configured disk/prefix, updates `stored_*` fields, and records basic cache metrics. Resilient to transient errors.

- `app/Jobs/ProcessListingPayload.php`
  - Thin wrapper to call `Listing::upsertFromPayload()` with a raw IDX-like payload; used by tests and future pipelines.

- Queues
  - Database queue driver by default (`QUEUE_CONNECTION=database`). Jobs like media sync/download use the `media` queue.


## Console Commands

- `app/Console/Commands/ListingMediaBackfill.php`
  - Enqueues `SyncIdxMediaForListing` jobs for missing/all listings, with chunking, limits, and queue selection.

- `app/Console/Commands/ListingMediaPrune.php`
  - Scans storage for orphaned files under the media prefix, and optionally deletes them (`--force`).


## Views and UI

- Layouts
  - `resources/views/components/layouts/site.blade.php`: Public site shell with Flux header/sidebar, auth menu, and footer. Injects GA4 client snippet when `AnalyticsSetting::clientSetting()` is enabled and sets up Vite + Flux appearance.
  - `resources/views/components/layouts/app.blade.php`: Authenticated app layout used by admin/settings Volt pages.

- Public pages
  - `resources/views/welcome.blade.php` + `welcome/partials/*`: Landing page sections: hero, product, roadmap, pipeline, database diagnostics, IDX feed, tech, CTA. The IDX section reflects connection state and renders live cards when available.
  - `resources/views/listings.blade.php`: Public listings grid (uses `ListingPresentation` helpers and Flux components).
  - `resources/views/listings/show.blade.php`: Public listing detail with status badge, media gallery, and facts.

- Admin pages (Volt)
  - `resources/views/livewire/admin/listings/index.blade.php`: Admin workspace; search/filter/pagination; suppression/unsuppression with audit log entries; purge/seed actions gated by policies.
  - `resources/views/livewire/admin/listings/show.blade.php`: Admin listing detail; metadata panels, media, history; suppression state and history when migrations present.
  - `resources/views/livewire/admin/feeds/index.blade.php`: IDX/VOW connection checks, quick preview, and import queue triggers (with duplicate-run protection and cache-driven progress).
  - `resources/views/livewire/admin/settings/analytics.blade.php`: Manage GA4 client/server integration with validation and helpful copy.

- Auth & Settings (Volt)
  - `resources/views/livewire/auth/*.blade.php`: Login/register/forgot/reset/verify and two‑factor challenge.
  - `resources/views/livewire/settings/*.blade.php`: Profile, password, appearance, and two‑factor settings (uses `ManagesTwoFactor`).

- UI building blocks
  - Flux components are used extensively (buttons, badges, inputs, menus, etc.).
  - App components under `resources/views/components/ui/*` provide styled primitives (`card`, `section-badge`, `section-header`).

- Assets
  - `resources/css/app.css` and `resources/js/app.js` import Tailwind and Flux, declare theme variables, and set up content sources for Tailwind v4.


## Configuration & Environment

- `config/services.php`
  - IDX/VOW credentials map directly to PropTx RESO OData API:
    - `services.idx.base_uri` ← `IDX_BASE_URI` (e.g. `https://query.ampre.ca/odata/`).
    - `services.idx.token` ← `IDX_TOKEN` (bearer token).
    - `services.idx.run_live_tests` ← `RUN_LIVE_IDX_TESTS` (enables live test in CI/dev when `1`).
    - `services.idx.homepage_fallback_to_active` ← `IDX_HOMEPAGE_FALLBACK_ACTIVE`.
    - VOW mirrors IDX: `VOW_BASE_URI` and `VOW_TOKEN` (base defaults to `IDX_BASE_URI`).

- `config/media.php`
  - `MEDIA_DISK`, `MEDIA_PATH_PREFIX`, `MEDIA_AUTO_DOWNLOAD` configure media storage and whether new media from payloads is queued for download.

- `resources/views/partials/head.blade.php`
  - Injects the GA client snippet when `AnalyticsSetting::clientSetting()` is enabled and a measurement ID is present.

- `.env.example`
  - Provides sane local defaults (MySQL on TCP 3307; DB name `power_of_sale`; queue `database`; mail `log` driver; Redis `phpredis`).
  - Includes all required IDX/VOW and media keys; read this first to prepare a local `.env`.


## Database & Migrations

Key tables are created and evolved by the migrations under `database/migrations` (timestamped for ordering). Highlights:

- `create_users_table.php` + subsequent user augmentations (`add_two_factor_columns`, `add_role_and_status_to_users_table`, `add_password_rotation_fields_to_users_table`).
- Listings core: `create_listings_table.php` with evolutions (`add_source_and_municipality_to_listings_table`, `add_display_status_modified_at_index_to_listings_table`, `add_modified_at_index_to_listings_table`).
- Media: `create_listing_media_table.php` + `add_storage_columns_to_listing_media_table.php` to support stored files post-download.
- Status history: `create_listing_status_history_table.php`.
- Suppressions: `create_listing_suppressions_table.php` + suppression fields added to `listings`.
- Dimensions: `create_sources_table.php`, `create_municipalities_table.php`.
- Saved searches: `create_saved_searches_table.php` (for future use).
- Audits: `create_audit_logs_table.php`.
- Analytics settings: `create_analytics_settings_table.php` + `add_client_enabled_to_analytics_settings_table.php`.
- Queue/meta: `create_jobs_table.php`, `create_cache_table.php`.

Factories exist for all core models under `database/factories/**` to support tests and seeding scenarios.


## Testing Overview

- Test runner: Pest v3 (`php artisan test`).
- Feature coverage includes: public pages (homepage, listings), auth flows, dashboard, admin Volt pages, IDX client request/shape filters, ingestion jobs, media jobs, console commands, import source priority, and schema presence.
- Unit coverage includes: policies, value helpers and presentation, DTOs, and model accessors (e.g., `ListingMedia::public_url`).
- Live IDX smoke test (`tests/Feature/HomepageLiveIdxFeedTest.php`) is opt‑in and requires `RUN_LIVE_IDX_TESTS=1` and valid IDX credentials.

Useful filters while iterating:

- Run a single file: `php artisan test tests/Feature/PublicListingsTest.php`
- Filter by name: `php artisan test --filter=ImportIdxPowerOfSale`


## Conventions & Tips

- Always use Eloquent with relations and eager loading to avoid N+1s (see controllers and Volt pages for patterns).
- Keep IDX/VOW creds only in `.env` → `config/services.php`; don’t read `env()` outside config.
- Volt components co‑locate PHP + Blade; use attributes (`#[Layout]`, `#[Computed]`, `#[Url]`, `#[Locked]`) for clarity.
- Tailwind v4 only; do not use deprecated v3 directives. Global CSS imports live in `resources/js/app.js` and `resources/css/app.css`.
- Queue workers: start a worker for the `media` queue when enabling media auto-download: `php artisan queue:work --queue=media,default`.
- Caching: IDX homepage/preview and import progress are cached for a few minutes. Clear via `php artisan cache:clear` or explicit `Cache::forget()` keys noted in services/jobs.


## Quick File Index (what to look for where)

- Routes: `routes/web.php`, `routes/auth.php`, `routes/console.php`
- Middleware: `app/Http/Middleware/EnsureUserIsAdmin.php`
- Controllers: `app/Http/Controllers/*`
- Models: `app/Models/*` (+ `app/Models/Concerns/*`)
- Policies: `app/Policies/*` (+ `app/Providers/AuthServiceProvider.php`)
- Services: `app/Services/Idx/*`, `app/Services/GoogleAnalytics/*`
- Jobs: `app/Jobs/*`
- Commands: `app/Console/Commands/*`
- Views: public (`resources/views/*.blade.php`), admin & settings (`resources/views/livewire/**`), components (`resources/views/components/**`)
- Config: `config/services.php`, `config/media.php`, plus Laravel defaults (auth, database, queue, session)
- Assets: `resources/js/app.js`, `resources/css/app.css`, `vite.config.js`
- Tests: `tests/Feature/**`, `tests/Unit/**`


## Getting Oriented Locally

- Environment
  - Copy `.env.example` → `.env` and fill the DB and IDX/VOW variables as needed.
  - Migrate: `php artisan migrate` (plus `php artisan storage:link` if serving media locally).

- Dev servers
  - PHP: `php artisan serve` or your preferred web server.
  - Vite: `npm install` then `npm run dev` for asset changes to reflect.
  - Queue: `php artisan queue:work --queue=media,default` when running imports and media download.

- First login/admin
  - Register a user. If no admins exist, the first authenticated user is elevated by `EnsureUserIsAdmin` on first admin route visit.

- Demo data
  - Use the admin listings workspace to seed (`Seed fake listings`) for UI walkthroughs, or run the IDX/VOW imports if you have credentials.


---

If anything is unclear or you spot drift between this document and the code, update this file alongside your change so new teammates keep their footing.
