# Power of Sale Listing Platform – Build Plan

## Stack Overview

- **Backend Framework:** Laravel 12.x with the Livewire starter kit on PHP 8.3. Fortify manages authentication and profile flows, while Volt single-file components drive interactive pages.
- **Frontend Tooling:** Vite 7 build pipeline with Tailwind CSS 4, Flux UI Free components, and the Alpine runtime bundled with Livewire. Avoid introducing extra UI libraries (charts, maps, etc.) until the requirement and ownership are confirmed.
- **Database:** MySQL 8 as the primary datastore. Keep core entities (listings, sources, municipalities, change logs, saved searches) normalized; reserve JSON columns for unpredictable payloads.
- **Queues & Caching:** Start with the database drivers to minimize local setup. Document thresholds for switching to Redis and obtain approval before introducing Horizon or other queue dashboards.
- **File Storage:** Local driver for development and automated tests. Plan for S3 with SSE-S3 encryption for exports and attachments once stakeholders approve the retention policy.
- **Observability:** Laravel logging plus Laravel Pail for local log tailing. Expand to hosted error tracking or metrics (Sentry, OpenTelemetry) only after a written proposal and approval.
- **Testing & Tooling:** Pest 3 for all tests, Laravel Pint for formatting, GitHub Actions (or equivalent) for CI running `composer test` and `npm run build`. Treat Tinker as a local-only tool.

## Local Tooling Prerequisites

The starter kit has been validated on WSL with the following versions; ensure local environments meet or exceed them before running the project commands listed below:

- **PHP 8.3.26** — satisfies the Laravel 12 requirement of PHP ≥ 8.2 (`php -v`).
- **Node.js 20.19.5** — Vite 7 and Tailwind CSS 4 require Node ≥ 18 (`node -v`).
- **npm 11.6.2** — ships with Node 20 and supports the Vite pipeline (`npm -v`).
- **MySQL 8.0.43** — aligns with the MySQL 8 baseline captured in this build plan (`mysql --version`).

## Delivery Milestones

| Milestone | Duration | Objectives | Acceptance Evidence |
| --- | --- | --- | --- |
| **M0 – Foundation Ready** | Week 1 | - Validate starter kit baseline (`php artisan serve`, `npm run dev`).<br>- Harden `.env.example` with required keys: database, mail, queue, storage placeholders.<br>- Establish base Volt + Flux navigation and landing page content.<br>- Configure CI workflow (tests + lint) and capture environment setup notes in README. | - `php artisan test` passes from a clean checkout.<br>- `npm run build` succeeds without warnings.<br>- README quick-start aligned with verified local steps. |
| **M1 – Data Model & Admin Shell** | Weeks 2–3 | - Design migrations for listings, sources, status history, media assets, audit log.<br>- Create Eloquent models, factories, and seeders for representative listing data.<br>- Build admin Volt screens for browsing, filtering, and inspecting seeded listings.<br>- Secure admin routes with Fortify auth and authorization policies. | - `php artisan migrate:fresh --seed` provisions dummy listings without errors.<br>- Volt component tests cover table filtering/pagination.<br>- Authenticated admin can list and view records end-to-end. |
| **M2 – Data Intake & Normalisation** | Weeks 4–5 | - Implement CSV import workflow with validation and queued processing.<br>- Normalise imported records into canonical tables, capturing source metadata and change history.<br>- Surface import status and retry actions within an admin dashboard.<br>- Introduce nightly data integrity checks (duplicates, stale listings). | - Pest feature tests assert import happy-path, validation failures, and duplicate detection.<br>- Queue job tests ensure canonical models persist with audit trail.<br>- Admin UI displays ingest history with live statuses. |
| **M3 – Public Portal & Notifications** | Weeks 6–7 | - Build public search experience (filters, sorting, map placeholder, listing detail view).<br>- Allow authenticated users to save searches and subscribe to email alerts.<br>- Add informational pages (FAQs, compliance statements) and a contact form.<br>- Apply rate limiting and confirmation flows for notifications. | - HTTP tests cover public search, detail, and saved search flow.<br>- Mail fake assertions verify alert dispatch rules.<br>- Basic accessibility scan (axe/Pa11y) documented in QA notes. |
| **M4 – Hardening & Launch Prep** | Weeks 8–9 | - Load-test critical endpoints; optimise hot queries with eager loading and caching where justified.<br>- Review authorization policies, security headers, and two-factor enforcement options.<br>- Finalise runbooks: backup strategy, incident response, deployment checklist.<br>- Execute stage-to-prod deployment rehearsal with rollback and data snapshot. | - Performance report committed in `docs/runbook.md` annex.<br>- Security scans (`composer audit`, `npm audit`) and Pint formatting pass.<br>- Cutover checklist signed off by stakeholders. |

## Cross-Cutting Concerns

- **Access Control:** Use policies and Fortify features (password confirmation, optional 2FA) for admin actions and saved search management. Avoid role checks directly in Blade views.
- **Background Jobs:** Keep ingestion/import logic in queued jobs with graceful failure handling. Document migration path to Redis/Horizon when job concurrency requirements exceed database queue capabilities.
- **Data Quality:** Standardize addresses (municipality, postal code) via dedicated helpers. Schedule nightly validation jobs with clear alerting to prevent stale or duplicated listings.
- **Front-End Patterns:** Reuse Flux UI tables, forms, and modals before creating bespoke components. Ensure Tailwind tokens support dark mode toggled through the appearance settings Volt page.
- **Testing Strategy:** Expand Pest coverage with datasets for validation scenarios, Volt::test for interactive components, and HTTP tests for public endpoints. Keep factories up-to-date with edge cases (missing price, conditional statuses).

## Documentation & Handoff

- Maintain `docs/runbook.md` with environment setup, scheduled jobs, import instructions, and escalation contacts.
- Keep README deployment, testing, and environment sections in sync with milestone deliverables.
- Provide admin training notes and a short walkthrough (video or written) when Volt dashboards evolve.
- Store compliance artifacts (terms of use, privacy summaries) inside `docs/compliance/`, version-controlled with stakeholder sign-off.
