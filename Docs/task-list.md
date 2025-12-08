# Power of Sale Listing Platform – Task List

## M0 – Foundation Ready

-   [x] Verify local tooling (`php`, `node`, `npm`, `mysql`) meets project version constraints and document prerequisites.
-   [x] Run `composer install` and `npm install`; confirm `php artisan serve` and `npm run dev` start without errors.
-   [x] Configure `.env.example` with required keys (database, mail, queue, storage) and add any missing placeholders.
-   [x] Ensure database connection defaults allow local development with MySQL credentials and capture steps in README.
-   [x] Set up database queue and cache tables via Artisan migrations; seed initial Fortify configuration values if needed.
-   [x] Establish primary Volt + Flux layout shell (navigation, header, footer) and wire it into the welcome/dashboard views.
-   [x] Enable Tailwind 4 build pipeline with Vite, confirming hot reloading and production builds output expected assets.
-   [x] Create CI workflow that runs `composer test`, `npm run build`, and `vendor/bin/pint --dirty` on pull requests.
-   [x] Document setup workflow and troubleshooting tips in README and `docs/runbook.md`.

## M1 – Data Model & Admin Shell

-   [x] Design database schema for listings, sources, municipalities, status history, media assets, saved searches, and audit logs; create migrations via `artisan make:migration`.
-   [x] Define Eloquent models with relationships, casts, and attribute accessors; create factories and seeders for representative data sets.
-   [x] Implement database seeders to populate dummy listings, municipalities, and source organizations for development.
-   [x] Introduce admin and subscriber user roles with Volt-driven user management for admins to view, invite, suspend, and update other users.
-   [x] Build Volt admin pages leveraging Flux tables for listing browse, filter, paginate, and quick detail preview states.
-   [x] Add listing detail Volt view with Flux panels showing metadata, photos, and change history.
-   [x] Add admin listing suppression workflow (soft-unpublish with audit trail & expiry) while reserving hard deletes for automated stale/orphan cleanup.
-   [x] Provide admins the ability to trigger password reset emails or forced credential rotations for selected users.
-   [x] Implement authorization policies and gates leveraging the new role model; ensure policy tests cover allow/deny paths.
-   [x] Write Pest feature tests covering admin listing browse, filter, and detail actions; include Volt component tests for UI state.
-   [x] Update navigation to expose admin dashboard routes only for authenticated users.
-   [x] Integrate Google Analytics configuration into admin settings and surface key metrics within the dashboard.
-   [x] Refresh README and runbook with new commands (migrations, seeders, admin URLs).

## M2 – Data Intake & Normalisation

-   [x] PropTx API and auth flow
-   [x] Ingest new listings
-   [x] Ingest and update existing listings
-   [x] Normalise PropTx payloads into the canonical listing structure while persisting raw payload snapshots for auditing.
-   [x] Implement change-log tracking (created/updated/deactivated) and associate with listing history table.
-   [x] Surface PropTx sync statuses, errors, and metrics within an admin dashboard view.
-   [x] Add retry, backoff, and rollback controls for failed API synchronisations; ensure idempotency on reprocessing.
-   [x] Write queue job, API client, and dashboard tests covering happy paths, validation errors, and duplicate detection.
-   [x] Document PropTx integration workflow (API endpoints, rate limits, error codes) in runbook.
-   [x] Rework Power-of-Sale detection: replicate all `TransactionType='For Sale'` listings, normalize remarks locally, and classify PoS in-app (see `ImportPosLast30Days` job).

## M2.1 – Listing Media Ingestion & Storage

-   [x] Migration: add storage columns to listing_media
-   [x] Config: media env vars + config/media.php
-   [x] Job: DownloadListingMedia to fetch/store images
-   [~] Throttle: media queue in place (rate-limit pending)
-   [x] Dispatch: queue downloads from syncMedia (behind MEDIA_AUTO_DOWNLOAD flag)
-   [x] UI: prefer Storage URL, fallback remote (model accessor + views)
-   [x] Tests: job + URL accessor
-   [x] Command: listing-media:backfill (missing downloads)
-   [x] Command: listing-media:prune (dry-run, schedule)
-   [ ] Runbook: storage:link, env, worker notes
-   [x] Metrics: job success/failure counters
-   [ ] Retention: policy for soft-deleted listings
-   [ ] Optional: thumbnails/variants (pending)
-   [ ] Schedule: nightly duplicate/stale checks

Media Resource Requirements (from API Docs)

-   [x] Images must be fetched from the API's Media resource (not embedded in Property payloads): `/odata/Media`.
-   [x] Filter for listing photos by `ResourceName eq 'Property'`, `ResourceRecordKey eq <ListingKey>`, and active photos only (e.g., `MediaCategory eq 'Photo'`, `MediaStatus eq 'Active'`). Order deterministically with `$orderby=MediaModificationTimestamp,MediaKey` and use `$top=1` to pick a primary image when needed.
-   [x] Select only the needed fields for performance (e.g., `$select=MediaURL,MediaType,ResourceName,ResourceRecordKey,MediaModificationTimestamp`).
-   [x] Be aware: `Media.ModificationTimestamp` can change without changing the listing's `ModificationTimestamp`. Track `PhotosChangeTimestamp`/`DocumentsChangeTimestamp` on Property to detect media changes.
-   [ ] For replication/backfill, page Media by `ModificationTimestamp,MediaKey` and dedupe by `ResourceRecordKey`.

## M3 – Public Portal & Notifications

-   [ ] Build public search page with Volt components for filters (price, municipality, property type) and sortable result tables.
-   [ ] Implement listing detail page for public users with gallery, property facts, contact actions, and compliance banners.
-   [ ] Add saved search feature for authenticated users, including create/edit/delete flows and validation.
-   [ ] Configure email notification preferences using Fortify profile settings and Volt appearance page integration.
-   [ ] Create notification dispatch jobs leveraging queued mail; use Markdown mail templates aligned with brand styling.
-   [ ] Add informational static pages (FAQ, compliance, contact) using Volt or Blade partials with shared layout.
-   [ ] Introduce rate limiting for contact forms and notification endpoints to prevent abuse.
-   [ ] Write HTTP and Volt tests covering public search, listing view, saved search CRUD, and notification opt-in/out.
-   [ ] Update README with public feature overview and saved search instructions.

## M4 – Hardening & Launch Prep

-   [ ] Profile critical queries; add eager loading, scopes, and indexes where necessary to meet performance targets.
-   [ ] Evaluate caching strategy (per-query cache, cached counts) and document when to promote to Redis.
-   [ ] Implement security headers (CSP, HSTS, X-Frame-Options) via middleware or config; confirm against OWASP checklist.
-   [ ] Review Fortify settings for password confirmation, 2FA prompts, and recovery codes; update policies/tests accordingly.
-   [ ] Configure scheduled backups and verify restore procedure in non-production environment.
-   [ ] Conduct load tests on key endpoints; record methodology and outcomes in `docs/runbook.md`.
-   [ ] Run `composer audit`, `npm audit`, and `vendor/bin/pint --dirty`; address findings or document mitigation.
-   [ ] Produce deployment checklist including environment variables, artisan commands, queue workers, and roll-back plan.
-   [ ] Run stage-to-production rehearsal, including database snapshot, migration dry-run, and smoke test validation.

## Cross-Cutting & Continuous Tasks

-   [ ] Maintain `docs/runbook.md` with environment updates, cron schedules, user roles, and escalation contacts.
-   [ ] Keep compliance artifacts (terms, privacy, data retention approvals) current within `docs/compliance/`.
-   [ ] Implement logging conventions (structured context, correlation IDs for background jobs) and monitor via Laravel Pail.
-   [ ] Configure exception reporting (local: Log stacktrace; production: integrate approved provider when authorized).
-   [ ] Establish code review checklist emphasising tests, Volt conventions, accessibility, and data integrity.
-   [ ] Track technical debt items in backlog (temporary flags, manual migrations, future Redis adoption).
-   [ ] Prepare admin training materials (written walkthrough or video) once dashboards and workflows stabilize.
-   [ ] Schedule regular QA cycles covering accessibility, browser/device matrix, and data accuracy spot checks.
-   [ ] Wire up Fortify email verification end-to-end (model implements `MustVerifyEmail`, feature enabled, tests updated) ahead of any non-local deployment.
-   [ ] Gate or remove the public database diagnostics/listing samples on `welcome.blade.php` before exposing the app outside local development.
-   [ ] Replace demo credentials and committed secrets (`.env`, seeded passwords) with environment-specific values and document the rotation/override process.
