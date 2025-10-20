# Power of Sale Listing Platform – Build Plan

## Stack Overview

-   **Backend Framework:** Laravel 12.x with the **Livewire** starter kit on **PHP 8.3**; configure `composer.json` platform to lock PHP, and confirm package compatibility against the targeted minor release before upgrading.
-   **Frontend Tooling:** Vite build pipeline, Tailwind CSS 4.1.14 (current project baseline), Alpine.js (bundled with the Livewire starter kit), and Chart.js for visualizations once approved.
-   **Admin Interface:** Livewire Volt single-file components with Flux UI Free widgets for internal dashboards, verification workflows, queue monitoring, and manual review flows; lean on reusable Flux tables, forms, and modals before introducing new UI stacks.
-   **Database:** MySQL 8 as the primary relational database. Use JSON columns to store raw scrape payloads and audit metadata.
-   **Caching & Queues:** Redis for queue, cache, and rate-limit storage. Laravel Horizon (subject to approval/install) provides queue monitoring. Document the scale path to managed Redis (e.g., ElastiCache) once workloads exceed single-node capacity.
-   **Scraping Runtime:** Node.js 20 LTS with Playwright (Chromium) launched from Laravel jobs via Symfony Process. Pin Playwright/browser versions in `package.json` and CI to avoid flaky scraping runs.
-   **Storage & Files:** Amazon S3 with SSE-KMS encryption for export files, session artifacts, and verification documents. Local filesystem driver used only in development.
-   **Observability:** Start with Laravel logging and exception reporting; propose Laravel Pail, Spatie Laravel Health, Sentry, OpenTelemetry instrumentation, and a Prometheus exporter as add-ons once the dependency footprint is approved.
-   **Dependency Footprint & Approvals:** New composer/npm packages (e.g., Horizon, Health, Excel, Sentry SDK, Playwright, Chart.js, Cypress) require stakeholder sign-off and documented rationale in the build log before installation.

## Build Timeline & Milestones

| Milestone                                             | Duration   | Objectives                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            | Acceptance Tests                                                                                                                                                                                                                                                                                                                                                                                                                       |
| ----------------------------------------------------- | ---------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **M0 – Project Initialization & Dummy Data Showcase** | Week 1     | - Install Laravel 12 with Livewire starter kit.<br>- Configure Laravel Herd with PHP, MySQL, and Redis for local development, documenting Herd site/domain configuration.<br>- Set up `.env` templates, GitHub Actions skeleton, and baseline README.<br>- Scaffold foundational Volt admin layout and Flux UI navigation.<br>- Define database schema migrations for listings, raw ingests, verifications, exports, and audit logs.<br>- Seed the database with dummy property records (factories + seeder) and publish a Volt/Flux table that renders this sample data as an end-to-end smoke test. | - `php artisan test` (skeleton feature + unit tests).<br>- `npm run build` compiles assets without errors.<br>- `php artisan migrate:fresh --seed` loads dummy property data without errors.<br>- `php artisan test --filter=DummyPropertyTableTest` (or equivalent Volt component test) verifies the table renders seeded rows.<br>- Manual validation via the Herd-served URL confirms the dummy listings table appears as expected. |
| **M1 – Scraping Infrastructure**                      | Weeks 2–3  | - Build Playwright script to authenticate, perform search, paginate, and extract listing JSON (pending package approval).<br>- Create secure credential storage using Laravel env variables and encrypted secrets.<br>- Implement 2FA handling: default to manual admin entry of SMS codes using a Volt/Flux modal; optionally enable Twilio virtual SMS retrieval only with documented portal authorization.<br>- Persist raw scrape payloads, scrape session logs, and audit data in staging tables.<br>- Schedule scraping jobs with retry/backoff and monitoring (Horizon once approved).         | - Playwright smoke test (`npx playwright test scraping.spec.ts`).<br>- Pest feature test for `ScrapeListingsJob` storing payloads.<br>- Manual Volt-admin 2FA entry documented verifying both manual and (if enabled) automated flows, including approver attribution.                                                                                                                                                                 |
| **M2 – Normalization & Verification Workflow**        | Weeks 4–5  | - Implement Laravel pipeline to normalize listings (address parsing, price normalization, MLS ID dedupe) using a libpostal sidecar for canonical address formatting.<br>- Build a rule-driven verification service that inspects scraped metadata (status flags, keywords) and records reviewer overrides without relying on PDF parsing.<br>- Create Volt/Flux review screens for manual overrides and ambiguous cases.<br>- Establish business rules for false-positive filtering without machine learning, including low-confidence escalation rules.                                              | - Pest unit tests covering normalization rules and the verification service.<br>- Feature test ensuring only listings that pass rule evaluation (or receive manual approval) publish.<br>- Volt component test verifying the review workflow (`php artisan test --filter=VerificationWorkflowTest`).                                                                                                                                   |
| **M3 – Public API & Web Experience**                  | Weeks 6–7  | - Build REST JSON API endpoints for listings, stats, and exports with query parameters for city and keyword filters and explicit rate limits (per-IP and per-token).<br>- Develop public Livewire/Volt pages for search, filters, charts (Chart.js once approved), and CSV export triggers.<br>- Implement response caching with cache tags, ETag/Last-Modified headers, and conditional GET support.<br>- Ensure responsive design and WCAG 2.2 AA accessibility for public pages using Tailwind 4 utilities.                                                                                        | - Pest feature tests for API endpoints (`php artisan test --filter=PublicApiTest`).<br>- Jest/Vitest tests for front-end utilities (`npm run test`).<br>- Cypress end-to-end smoke suite hitting primary public flows (`npx cypress run --spec cypress/e2e/public.cy.ts`, pending approval/install).<br>- Accessibility CI check (`npx axe-cli http://localhost` or `npx pa11y-ci`).                                                   |
| **M4 – Exports, Reporting & Monitoring**              | Week 8     | - Integrate Laravel Excel (maatwebsite/excel, once approved) for CSV generation (manual and scheduled) with export quotas, pagination, and signed URL expirations.<br>- Add historical charts and dashboard metrics within Volt/Flux admin components (queue status, scrape counts, verification backlog age).<br>- Configure optional observability tooling (Health checks, Sentry, OpenTelemetry, Prometheus) following dependency approval workflow.<br>- Document runbook, alerting thresholds, and escalation paths tied to SLOs (e.g., 95% of scrapes < 15 minutes).                            | - Pest test ensuring export job produces CSV with expected columns and respects quotas.<br>- Scheduled command test verifying daily export registration.<br>- Observability smoke tests once tooling is installed (e.g., `php artisan health:check`, Prometheus scrape curl).                                                                                                                                                          |
| **M5 – Deployment Hardening & Launch**                | Weeks 9–10 | - Finalize environment configs, secrets management, and database backups.<br>- Harden security (HTTPS, rate limiting, audit logging).<br>- Prepare content (docs, FAQs, privacy terms).<br>- Execute staging and production deployment, smoke tests, and handover.                                                                                                                                                                                                                                                                                                                                    | - GitHub Actions pipeline green (all workflows).<br>- Forge deployment smoke test (`php artisan about` on production).<br>- Final regression suite (Pest + approved browser tests).                                                                                                                                                                                                                                                    |

Each milestone must pass all listed acceptance tests before moving to the next milestone. Regression suites should include prior milestone tests to avoid regressions. CI must build assets, execute approved browser tests, and publish artifacts before deployment approvals.

## Detailed Subsystem Specifications

### Authentication & Admin Access

-   Use Livewire starter kit’s authentication scaffolding with password + email verification.
-   Integrate Laravel Fortify features for session management and rate limiting.
-   Build Volt-based admin areas with Flux UI components for navigation, tables, and forms.
-   Role/permission management can rely on Laravel gates/policies initially; introduce packages (e.g., Spatie Laravel Permission) only with approval and document the rationale.

### Scraping & Two-Factor Handling

-   Launch Playwright via a Laravel queue job that spins a Node.js child process using Symfony Process (after dependency approval).
-   Store credentials (username, password) in Laravel’s encrypted configuration.
-   Maintain a source registry documenting terms of service, licensing, automation allowances, rate limits, and contact details for each portal. Obtain written authorization before enabling scraping or automated MFA.
-   Implement manual 2FA entry: when Playwright detects an SMS challenge, it pauses and dispatches a Livewire event surfaced through a Volt/Flux modal. Admin enters the received code (delivered via personal phone) into the modal; the job resumes with the provided code.
-   Implement automated 2FA retrieval as an opt-in path: integrate a Twilio virtual SMS number, persist webhook deliveries, log approver identity for each use, and allow the scraper to poll Twilio’s API for the latest code only when authorized.
-   Persist session storage state securely in S3 (SSE-KMS) to minimize repeated MFA prompts while honoring portal terms. Rotate sessions regularly and redact secrets from logs.

### Data Processing & Verification

-   Use Laravel Jobs/Pipelines to normalize scraped data and enforce deterministic rules (no machine learning).
-   Run address data through a libpostal container/sidecar to produce canonical street, city, province, and postal code values.
-   Evaluate scraped metadata (status flags, remarks, legal descriptions) with heuristic rules to confirm power-of-sale status, persisting evidence scores and reviewer notes. Escalate low-confidence matches to human review within Volt admin screens.
-   Track verification status (`pending`, `auto_verified`, `needs_review`, `rejected`) with audit columns (verifier, timestamp, reason) and maintain a reviewer feedback loop for rule tuning.

### Public API & UI

-   Expose REST endpoints under `/api/listings`, `/api/stats`, and `/api/exports` secured with per-IP and per-token rate limiting, conditional GET headers, and pagination defaults.
-   Public-facing Livewire/Volt components use Livewire actions and data binding for search, filtering, and pagination, avoiding duplicative REST fetch logic while keeping interactions server-driven.
-   Maintain REST endpoints to power third-party integrations and automated exports; document rate limits and auth requirements in OpenAPI specs.

### Security & Privacy Controls

-   Enforce principle of least privilege for database, Redis, and S3 credentials. Use GitHub Actions OIDC to assume AWS roles without long-lived IAM keys.
-   Encrypt sensitive columns (e.g., owner contact info, session tokens) using Laravel’s encrypted cast feature.
-   Apply S3 bucket policies denying public access, enforce TLS, configure lifecycle policies, and ensure signed URLs expire quickly.
-   Implement a log redaction policy covering credentials, MFA codes, and session cookies. Regularly review for PII leaks.
-   Document privacy policy, data retention windows, and legal justification for data collection.
-   Maintain audit logs for all admin actions using Activity log capabilities (add package only after approval if core logging proves insufficient).

### Data Model & Storage Design

-   `listings` table: `mls_id` (nullable, unique), `address_digest` (SHA-256 of normalized address + postal code, unique), `status`, `pos_verified_at`, `pos_verification_confidence`, `source_registry_id`, and JSON columns for raw payload snapshots.
-   `verification_evidence` table: `listing_id`, `evidence_type` (`keyword`, `status_flag`, `manual_note`), `payload` (JSON snapshot of supporting data), `confidence`, `recorded_by` for full auditability.
-   `ingest_sessions` table: `source`, `started_at`, `finished_at`, `result`, `errors` (JSON), `approved_by` (admin who authorized MFA or automation).
-   Apply foreign keys, cascading deletes where appropriate, and maintain historical tables (`listing_verification_logs`, `export_audits`) via event listeners.
-   Add MySQL 8 full-text indexes on normalized text fields with curated stopword lists for keyword search. Evaluate Elasticsearch or OpenSearch if advanced FTS is needed later and secure approval before adoption.

## Deployment Plan (Laravel Forge)

1. **Provision Infrastructure**

    - Use Laravel Forge to provision a production MySQL 8 server.
    - Use Laravel Forge to provision a Redis instance on the same server for low-latency queue operations and document migration path to managed Redis (e.g., ElastiCache) for high availability.
    - Create an S3 bucket with appropriate IAM roles for storage of exports and session artifacts (SSE-KMS, block public access).

2. **Forge Server Setup**

    - Deploy a Forge provisioned Ubuntu 22.04 server.
    - Attach domain and issue Let’s Encrypt SSL certificate.
    - Configure server to use PHP 8.3, Nginx, and Supervisor for queue workers.

3. **Forge Site Configuration**

    - Connect GitHub repository to Forge with the `main` branch as default.
    - Configure deployment script (assets built in CI; script avoids Node tooling in production):
        ```bash
        #!/bin/bash
        set -euo pipefail
        cd /home/forge/{{DOMAIN}}
        git pull origin main
        composer install --prefer-dist --no-dev --optimize-autoloader
        php artisan migrate --force
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
        php artisan event:cache
        php artisan storage:link || true
        php artisan queue:restart --max-memory=256 --timeout=3600
        ```
    - Set environment variables via Forge UI, including database credentials, Redis URL, S3 keys, Playwright configuration, Twilio credentials, logging endpoints, OpenTelemetry exporter settings, and Prometheus authentication.

4. **Queue & Scheduler**

    - Use Forge’s Daemon to run `php artisan queue:work --queue=scraping,default --sleep=3 --tries=2 --max-time=3600 --max-jobs=100 --memory=256`.
    - Configure Forge Scheduler to run `php artisan schedule:run` every minute.

5. **Verification & Smoke Tests**

    - After deployment, run on server (Forge SSH or Envoyer hook):
        ```bash
        php artisan migrate:status
        php artisan config:show app.name
        php artisan about
        ```
    - Execute remote Pest test suite against staging before production cutover. Browser-based suites (Playwright/Cypress/Volt component tests) remain in CI; only green pipelines may deploy.

6. **Monitoring & Backups**

    - Enable automated database backups (daily) and S3 lifecycle policy for exports with retention tiers.
    - Configure alerting integrations (Slack) for Forge deployments, queue health, Prometheus alertmanager, and health check failures once the related tooling is approved/installed.

7. **Cutover Procedure**
    - Run full regression suite on staging environment.
    - Perform content freeze and data migration if necessary.
    - Switch DNS to Forge-managed server once smoke tests pass, using blue/green cutover or staged TTL reductions.

## Documentation & Handoff

-   Maintain `docs/runbook.md` with operational procedures, 2FA handling instructions, and escalation contacts.
-   Update README with quick-start commands and testing matrix.
-   Provide admin training on Volt/Flux dashboards and verification workflow prior to launch.
-   Archive legal approvals, portal terms of use, and data retention policies within `docs/compliance/` for auditability.
