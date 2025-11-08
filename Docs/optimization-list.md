Optimization Plan

- API & Design
  - [done] Centralize shared RESO OData filters (Power of Sale).
  - [done] Introduce a ListingTransformer to remove reflection and share mapping.
  - [done] DRY select lists and IDX/VOW request wiring (base headers, tokens, Prefer).

- Data Access & Performance
  - [done] Add an index on `listings.modified_at`.
  - [done] Add pooled media lookups with a time budget for primary images.
  - [done] Add a composite index on (`display_status`, `modified_at`) to support filtered/sorted queries.
  - [done] Cache homepage DB metadata checks (connection + table/table columns) for ~60s to avoid repeated schema probes.
  - [next] Consider `simplePaginate()` for very large listing tables when total counts are not required (optional).
  - [next] Validate production query plans with `EXPLAIN` for listing filters and add supporting indexes only if required (optional).

- Queueing & Concurrency
  - [done] Add overlap-prevention for IDX/VOW imports.

- Model Consistency
  - [done] Prefer `casts()` method across models for consistency with Laravel 12.

- Caching & Metrics
  - [done] Make analytics summary cache key include property identifier.
  - [done] Batch IDX metrics writes.
  - [done] Memoize admin aggregates (status counts, top municipalities, db stats, price stats, suppression counts) for 30–60s to reduce repeated aggregate queries.
  - [done] Cache IDX primary image URL lookups per listing key for 10–15 minutes to further reduce media requests.

- Routing & Controllers
  - [done] Move complex route closures to invokable controllers.

- Testing
  - [done] Expand tests for transformer mapping and IDX caching branches.
  - [done] Add `.env.testing` with `RUN_LIVE_IDX_TESTS=0` to avoid live API calls by default.
  - [done] Run tests in parallel locally/CI (`php artisan test --parallel`) to reduce suite time.

Later

- Authentication & Fortify
  - [later] Define `RateLimiter::for('login', ...)` to enforce per email + IP login throttling in line with `config('fortify.limiters.login')`.

Status

- No pending items marked as in-progress.
