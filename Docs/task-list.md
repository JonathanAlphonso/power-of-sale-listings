# Power of Sale Listing Platform – Task List

## M0 – Foundation Ready

- [x] Verify local tooling (`php`, `node`, `npm`, `mysql`) meets project version constraints and document prerequisites.
- [x] Run `composer install` and `npm install`; confirm `php artisan serve` and `npm run dev` start without errors.
- [x] Configure `.env.example` with required keys (database, mail, queue, storage) and add any missing placeholders.
- [x] Ensure database connection defaults allow local development with MySQL credentials and capture steps in README.
- [x] Set up database queue and cache tables via Artisan migrations; seed initial Fortify configuration values if needed.
- [x] Establish primary Volt + Flux layout shell (navigation, header, footer) and wire it into the welcome/dashboard views.
- [x] Enable Tailwind 4 build pipeline with Vite, confirming hot reloading and production builds output expected assets.
- [x] Create CI workflow that runs `composer test`, `npm run build`, and `vendor/bin/pint --dirty` on pull requests.
- [x] Document setup workflow and troubleshooting tips in README and `docs/runbook.md`.

## M1 – Data Model & Admin Shell

- [x] Design database schema for listings, sources, municipalities, status history, media assets, saved searches, and audit logs; create migrations via `artisan make:migration`.
- [x] Define Eloquent models with relationships, casts, and attribute accessors; create factories and seeders for representative data sets.
- [x] Implement database seeders to populate dummy listings, municipalities, and source organizations for development.
- [ ] Introduce admin and subscriber user roles with Volt-driven user management for admins to view, invite, suspend, and update other users.
- [ ] Build Volt admin pages leveraging Flux tables for listing browse, filter, paginate, and quick detail preview states.
- [ ] Add listing detail Volt view with Flux panels showing metadata, photos, and change history.
- [ ] Allow admins to delete individual listings with confirmation flows and audit log entries.
- [ ] Provide admins the ability to trigger password reset emails or forced credential rotations for selected users.
- [ ] Implement authorization policies and gates leveraging the new role model; ensure policy tests cover allow/deny paths.
- [ ] Write Pest feature tests covering admin listing browse, filter, and detail actions; include Volt component tests for UI state.
- [ ] Update navigation to expose admin dashboard routes only for authenticated users.
- [ ] Integrate Google Analytics configuration into admin settings and surface key metrics within the dashboard.
- [ ] Refresh README and runbook with new commands (migrations, seeders, admin URLs).

## M2 – Data Intake & Normalisation

- [ ] Configure PropTx API credentials, authentication flow, and scheduled sync jobs to ingest new and updated listings.
- [ ] Normalise PropTx payloads into the canonical listing structure while persisting raw payload snapshots for auditing.
- [ ] Implement change-log tracking (created/updated/deactivated) and associate with listing history table.
- [ ] Surface PropTx sync statuses, errors, and metrics within an admin dashboard view.
- [ ] Add retry, backoff, and rollback controls for failed API synchronisations; ensure idempotency on reprocessing.
- [ ] Write queue job, API client, and dashboard tests covering happy paths, validation errors, and duplicate detection.
- [ ] Schedule nightly duplicate/stale listing checks via Laravel scheduler with reporting to admin inbox/log.
- [ ] Document PropTx integration workflow (API endpoints, rate limits, error codes) in runbook.

## M3 – Public Portal & Notifications

- [ ] Build public search page with Volt components for filters (price, municipality, property type) and sortable result tables.
- [ ] Implement listing detail page for public users with gallery, property facts, contact actions, and compliance banners.
- [ ] Add saved search feature for authenticated users, including create/edit/delete flows and validation.
- [ ] Configure email notification preferences using Fortify profile settings and Volt appearance page integration.
- [ ] Create notification dispatch jobs leveraging queued mail; use Markdown mail templates aligned with brand styling.
- [ ] Add informational static pages (FAQ, compliance, contact) using Volt or Blade partials with shared layout.
- [ ] Introduce rate limiting for contact forms and notification endpoints to prevent abuse.
- [ ] Write HTTP and Volt tests covering public search, listing view, saved search CRUD, and notification opt-in/out.
- [ ] Update README with public feature overview and saved search instructions.

## M4 – Hardening & Launch Prep

- [ ] Profile critical queries; add eager loading, scopes, and indexes where necessary to meet performance targets.
- [ ] Evaluate caching strategy (per-query cache, cached counts) and document when to promote to Redis.
- [ ] Implement security headers (CSP, HSTS, X-Frame-Options) via middleware or config; confirm against OWASP checklist.
- [ ] Review Fortify settings for password confirmation, 2FA prompts, and recovery codes; update policies/tests accordingly.
- [ ] Configure scheduled backups and verify restore procedure in non-production environment.
- [ ] Conduct load tests on key endpoints; record methodology and outcomes in `docs/runbook.md`.
- [ ] Run `composer audit`, `npm audit`, and `vendor/bin/pint --dirty`; address findings or document mitigation.
- [ ] Produce deployment checklist including environment variables, artisan commands, queue workers, and roll-back plan.
- [ ] Run stage-to-production rehearsal, including database snapshot, migration dry-run, and smoke test validation.

## Cross-Cutting & Continuous Tasks

- [ ] Maintain `docs/runbook.md` with environment updates, cron schedules, user roles, and escalation contacts.
- [ ] Keep compliance artifacts (terms, privacy, data retention approvals) current within `docs/compliance/`.
- [ ] Implement logging conventions (structured context, correlation IDs for background jobs) and monitor via Laravel Pail.
- [ ] Configure exception reporting (local: Log stacktrace; production: integrate approved provider when authorized).
- [ ] Establish code review checklist emphasising tests, Volt conventions, accessibility, and data integrity.
- [ ] Track technical debt items in backlog (temporary flags, manual migrations, future Redis adoption).
- [ ] Prepare admin training materials (written walkthrough or video) once dashboards and workflows stabilize.
- [ ] Schedule regular QA cycles covering accessibility, browser/device matrix, and data accuracy spot checks.
- [ ] Wire up Fortify email verification end-to-end (model implements `MustVerifyEmail`, feature enabled, tests updated) ahead of any non-local deployment.
- [ ] Gate or remove the public database diagnostics/listing samples on `welcome.blade.php` before exposing the app outside local development.
- [ ] Replace demo credentials and committed secrets (`.env`, seeded passwords) with environment-specific values and document the rotation/override process.
