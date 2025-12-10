# Power of Sale Listing Platform – Runbook

Operational guidance for maintaining the project across local development, CI, and hosted environments.

## Audience & Scope

- **Developers:** Day-to-day local setup, queues, testing, and troubleshooting.
- **DevOps / Release engineers:** CI expectations, deployment checklists, environment configuration.

## Environment Matrix

| Environment | Purpose | Notes |
| --- | --- | --- |
| Local (Windows + WSL2 + Herd) | Primary developer workflow | PHP runs via Herd on Windows; Node/Vite run in WSL. Use TCP (`127.0.0.1`) for DB access. |
| Local (WSL-only) | Optional alternate workflow | Install PHP, MySQL client, and Node inside WSL. Allows artisan servers/workers to run from Linux directly. |
| CI (GitHub Actions) | Pull request checks | Uses SQLite for tests (`composer test`) and enforces formatting with `vendor/bin/pint --dirty`. |
| Staging / Production (Forge) | Hosted environments | Ubuntu 22.04 + PHP 8.3. Configure MySQL, queues, scheduler, and HTTPS via Forge. |

## Setup Workflow

1. **Clone & install**
   ```bash
   composer install
   npm install
   ```
2. **Environment bootstrap**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
3. **Database configuration**
   - Update `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` with local credentials.
   - For Herd + WSL, keep `DB_HOST=127.0.0.1` so WSL tunnels to the Windows service.
4. **Provision schema**
   ```bash
   mysql -u <user> -p -e "CREATE DATABASE IF NOT EXISTS <database> CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   php artisan migrate --graceful
   ```
   Use the graceful flag whenever you pull new migrations so the command exits cleanly when no changes are pending.
5. **Seed reference data (optional)**
   ```bash
   php artisan db:seed --class=Database\Seeders\DatabaseSeeder
   ```
   The seeder provisions the administrator (`admin@powerofsale.test`) and analyst (`analyst@powerofsale.test`) demo accounts with the password `password`, and populates sample listings, municipalities, and analytics defaults.

### Flux UI Credentials

Flux components require valid credentials for Composer requests. Configure them locally without committing secrets:

```bash
composer config http-basic.composer.fluxui.dev <username> <license>
```

For CI, populate `FLUX_USERNAME` and `FLUX_LICENSE_KEY` secrets.

## Daily Development Commands

| Task | Command |
| --- | --- |
| Serve PHP via Herd | Managed automatically once site is added in Herd UI. |
| Serve PHP manually (WSL-only) | `php artisan serve` |
| Apply pending migrations | `php artisan migrate --graceful` |
| Seed demo data | `php artisan db:seed --class=Database\Seeders\DatabaseSeeder` |
| Run Vite dev server | `npm run dev` |
| Queue worker (database driver) | `php artisan queue:work` |
| Run scheduled tasks manually | `php artisan schedule:run` |
| Reset database | `php artisan migrate:fresh --seed` |

### IDX / PropTx Integration

- Credentials (PropTx) are configured via environment variables (see `.env.example`):
  - `IDX_BASE_URI` – e.g., `https://query.ampre.ca/odata/`
  - `IDX_TOKEN` – PropTx bearer token
  - `RUN_LIVE_IDX_TESTS` – set `1` to enable live smoke tests locally (defaults to `0`)
- Homepage feed:
  - Uses a Power of Sale remarks-based query with deterministic ordering (`ModificationTimestamp,ListingKey`).
  - Results are cached for 5 minutes using keys like `idx.pos.listings.4`.
  - Timeouts: 6s for Property requests; 3s for Media lookups.
- Clearing the cache:
  - All caches: `php artisan cache:clear`
  - Specific key (Tinker): `Cache::forget('idx.pos.listings.4')`
- OData limitations:
  - No `$expand`, no `$batch`, and no `tolower()`/`toupper()`; prefer `contains()` and simple boolean logic.
  - Use `$select` to limit fields and `$orderby` for stable paging. See `Docs/proptx-api-mapping.md`.

### Admin Workspace

After seeding, sign in with the administrator account to reach the secured workspace. All of the routes below require the `admin` middleware:

- `/dashboard` — admin overview with analytics summary and activity feeds.
- `/admin/listings` — browse, filter, suppress, and review listings.
- `/admin/users` — invite new teammates, rotate credentials, manage suspensions.
- `/admin/settings/analytics` — configure Google Analytics property, credentials, and client tracking.

## Quality Gates

- `composer test` – clears config cache and runs the Pest suite (see `composer.json`).
- `vendor/bin/pint --dirty` – formats touched files to project standards.
- `npm run build` – compiles production assets; required before deployment.

GitHub Actions replicate these commands for every push/PR to `develop` and `main`.

## Deployment Checklist (Forge or similar)

### Pre-Deployment

1. **Security Audits**
   - Run `composer audit` and address any critical/high vulnerabilities
   - Run `npm audit` and fix with `npm audit fix`
   - Run `vendor/bin/pint --dirty` to ensure code formatting

2. **Environment Preparation**
   - Prepare production `.env` with all required variables (see below)
   - Remove debug credentials and demo passwords
   - Set unique `APP_KEY` for production (never reuse dev keys)

3. **Database Backup**
   - Take full database backup before migration
   - Document rollback procedure

### Server Provisioning

1. **System Requirements**
   - PHP 8.3+ with extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pcre`, `pdo`, `tokenizer`, `xml`, `intl`
   - Node 20+
   - MySQL 8.0+ (or compatible)
   - Redis (optional, for high-traffic caching/queues)

2. **Required Environment Variables**
   ```bash
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain.com
   APP_KEY=base64:... # Generate with php artisan key:generate

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=pos_production
   DB_USERNAME=...
   DB_PASSWORD=...

   MAIL_MAILER=smtp
   MAIL_HOST=...
   MAIL_PORT=587
   MAIL_USERNAME=...
   MAIL_PASSWORD=...
   MAIL_FROM_ADDRESS=noreply@your-domain.com

   CACHE_STORE=database  # or redis
   QUEUE_CONNECTION=database  # or redis
   SESSION_DRIVER=database

   # IDX API (required for data feeds)
   IDX_BASE_URI=https://query.ampre.ca/odata/
   IDX_TOKEN=...

   # Media storage
   MEDIA_DISK=public
   MEDIA_AUTO_DOWNLOAD=true
   ```

### Deployment Script

```bash
# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader

# Run migrations with backup
php artisan migrate --force

# Build frontend assets
npm ci
npm run build

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Link storage (first deploy only)
php artisan storage:link

# Restart queue workers
php artisan queue:restart
```

### Post-Deployment

1. **Queue Workers** (Forge Daemon or Supervisor)
   ```bash
   php artisan queue:work --sleep=3 --tries=3 --timeout=1800
   ```

2. **Scheduler** (Crontab)
   ```bash
   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
   ```

3. **SSL/TLS**
   - Enable HTTPS via Let's Encrypt
   - Verify `APP_URL` uses `https://`
   - Test HSTS headers are present

4. **Smoke Tests**
   - Verify homepage loads
   - Test login/logout flow
   - Verify admin dashboard access
   - Check IDX data feed connectivity
   - Confirm queue workers are processing

### Rollback Procedure

1. Restore previous deployment code
2. Restore database from backup if migrations affected data
3. Clear caches: `php artisan cache:clear && php artisan config:clear`
4. Restart queue workers

## Troubleshooting Reference

| Symptom | Likely Cause | Resolution |
| --- | --- | --- |
| `php artisan serve` fails inside WSL | Herd already binds the port on Windows | Use Herd for serving, or stop Herd/serve from Windows. |
| Vite HMR fails to connect | Port 5173 occupied or origin mismatch | Stop conflicting process or change `server.port` in `vite.config.js`; ensure browser URL matches `APP_URL`. |
| Database connection refused from WSL | Using socket/hostname not reachable across environments | Set `DB_HOST=127.0.0.1` and confirm MySQL listens on TCP; verify firewall rules. |
| `npm install` errors on optional dependencies | Unsupported native binaries for platform | Remove optional deps or install with `npm install --no-optional`. |
| `composer install` prompts for Flux credentials | Credentials not configured | Run `composer config http-basic.composer.fluxui.dev …` or set `COMPOSER_AUTH`. |
| Slow file change detection | Repository located on `/mnt/c` | Polling enabled in Vite; optionally move repo to WSL home for inotify performance. |
| Queue jobs piling up | Worker stopped or DB locked | Restart worker, inspect `jobs` table, review exception logs (`storage/logs/laravel.log`). |

## Support & Escalation

- Update `Docs/task-list.md` as milestones close.
- Log recurring issues or environment overrides in this runbook so new contributors can ramp quickly.
## Queue Worker Setup

Data feed imports (including the “Import Both Now” button on `/admin/feeds`) dispatch jobs to the queue. A running queue worker is required in any environment where you expect imports to process.

### Local Development

- Start a worker in the foreground during a dev session:
  - `php artisan queue:work --sleep=3 --tries=3 --timeout=1800`
- Or run in the background and capture logs:
  - `nohup php artisan queue:work --sleep=3 --tries=3 --timeout=1800 > storage/logs/queue-worker.log 2>&1 &`
  - Inspect running workers: `ps aux | grep "queue:work"`
  - Tail logs: `tail -f storage/logs/queue-worker.log`

Notes:
- The project defaults to the `database` queue driver. Ensure migrations include the `jobs` and `failed_jobs` tables (`php artisan migrate`).
- In the test suite we force `QUEUE_CONNECTION=sync` to execute jobs inline; do not use this in development or production.
- On Windows + WSL: run the worker in the same environment that can reach MySQL. If PHP (WSL) can’t connect to `127.0.0.1:3307`, start the worker via Herd/Windows Terminal instead of WSL, or temporarily set `QUEUE_CONNECTION=sync` in `.env` for local testing.

### Production (Forge or Supervisor)

Use your platform’s process manager to keep workers alive across deploys:

- Forge Daemon (recommended):
  - Command: `php artisan queue:work --sleep=3 --tries=3 --timeout=1800`
  - User: your site user (e.g., `forge`)
  - Autostart on reboot and after deploy.

- Supervisor example (`/etc/supervisor/conf.d/laravel-worker.conf`):
  ```ini
  [program:laravel-worker]
  process_name=%(program_name)s_%(process_num)02d
  command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --timeout=1800
  autostart=true
  autorestart=true
  numprocs=1
  redirect_stderr=true
  stdout_logfile=/var/www/html/storage/logs/queue-worker.log
  stopwaitsecs=60
  ```
  Then: `sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start laravel-worker:*`

### Verifying Workers Are Running

- Process check: `ps aux | grep "queue:work"` should show at least one `php artisan queue:work` process.
- Health check by dispatching a test job:
  - `php artisan tinker` → `\Illuminate\Support\Facades\Bus::dispatchSync(new App\Jobs\ImportAllPowerOfSaleFeeds(50, 1));` (dev only)
  - Or queue a fake job and look for it to clear from the `jobs` table.
- Review logs for recent activity: `tail -n 200 storage/logs/laravel.log` and `storage/logs/queue-worker.log` (if using `nohup`/Supervisor).

If you see "Import queued" on `/admin/feeds` but nothing happens, a worker is not running.

## Listing Media Operations

Listing photos and media can optionally be downloaded and stored locally (on disk) instead of being served directly from the IDX API. This reduces external API load, improves page load times, and ensures media availability even if the source API is temporarily unavailable.

### Storage Setup

1. **Create the storage symlink** (required once per environment):
   ```bash
   php artisan storage:link
   ```
   This creates `public/storage` → `storage/app/public`. Verify with `ls -la public/storage`.

2. **Set directory permissions** (production):
   ```bash
   chmod -R 775 storage/app/public
   chown -R www-data:www-data storage/app/public
   ```

### Environment Variables

| Variable | Default | Description |
| --- | --- | --- |
| `MEDIA_DISK` | `public` | Laravel filesystem disk for storing downloaded media. |
| `MEDIA_PATH_PREFIX` | `listings` | Directory prefix within the disk (e.g., `storage/app/public/listings/`). |
| `MEDIA_AUTO_DOWNLOAD` | `false` | When `true`, media is automatically queued for download during listing sync. |
| `MEDIA_RATE_LIMIT_API` | `120` | Max IDX API requests per minute for media sync jobs. |
| `MEDIA_RATE_LIMIT_DOWNLOAD` | `60` | Max image download requests per minute. |

Example `.env` configuration:
```
MEDIA_DISK=public
MEDIA_PATH_PREFIX=listings
MEDIA_AUTO_DOWNLOAD=true
MEDIA_RATE_LIMIT_API=120
MEDIA_RATE_LIMIT_DOWNLOAD=60
```

### Rate Limiting

Media jobs are rate-limited to avoid overwhelming the IDX API (which allows 60,000 requests/minute) and to be a good API citizen. Two separate limiters are configured:

- **`media-api`**: Limits `SyncIdxMediaForListing` jobs (IDX API calls) to 120/minute by default
- **`media-download`**: Limits `DownloadListingMedia` jobs (image downloads) to 60/minute by default

When rate limited, jobs are automatically released back to the queue and retried later. Jobs have:
- 5 retry attempts
- 2-hour retry window
- Max 3 exceptions before failure

To increase throughput during backfill operations, temporarily raise limits in `.env`:

```bash
# High-throughput backfill (be mindful of API limits)
MEDIA_RATE_LIMIT_API=500
MEDIA_RATE_LIMIT_DOWNLOAD=200
```

### Media Queue Worker

Media download jobs use the `media` queue. Run a dedicated worker or include `media` in your worker's queue list:

```bash
# Dedicated media worker
php artisan queue:work --queue=media --sleep=3 --tries=3 --timeout=120

# Combined worker processing both default and media queues
php artisan queue:work --queue=default,media --sleep=3 --tries=3 --timeout=1800
```

For production, configure Supervisor or Forge Daemon with separate workers for high-volume media processing.

### Backfill Command

To queue media downloads for listings that are missing local copies:

```bash
# Backfill listings with no downloaded media (default)
php artisan listing-media:backfill

# Backfill all listings (re-download even if already stored)
php artisan listing-media:backfill --all

# Limit to 500 listings
php artisan listing-media:backfill --limit=500

# Use a specific queue
php artisan listing-media:backfill --queue=media-backfill
```

### Prune Command

Remove orphaned media files that no longer have corresponding database records:

```bash
# Dry-run: show what would be deleted
php artisan listing-media:prune

# Actually delete orphan files
php artisan listing-media:prune --force

# Prune from a specific disk
php artisan listing-media:prune --disk=public --force
```

**Caution:** Never use `--include-stored=true` unless you intend to delete all media files including those still referenced in the database.

### Monitoring Media Jobs

Media job metrics are cached and can be inspected via Tinker:

```php
// View download success/failure counts
Cache::get('media.download.success_count');
Cache::get('media.download.failure_count');

// View sync success/failure counts
Cache::get('media.sync.success_count');
Cache::get('media.sync.failure_count');
```

### Storage Structure

Downloaded media follows this path structure:
```
storage/app/public/listings/{listing_id}/{media_id}.{ext}
```

Example: `storage/app/public/listings/42/103.jpg`

### Retention Policy for Soft-Deleted Listings

When listings are soft-deleted, their media records and files remain on disk. Use the cleanup command to enforce retention:

```bash
# Dry-run: see what would be deleted (default: 30 days retention)
php artisan listings:cleanup-deleted

# Customize retention period
php artisan listings:cleanup-deleted --days=60

# Actually delete media files and records
php artisan listings:cleanup-deleted --force

# Also hard-delete the listing records (permanent removal)
php artisan listings:cleanup-deleted --hard-delete --force
```

This command is scheduled to run nightly (see Scheduler section). Manual runs should use dry-run first to preview actions.

### Troubleshooting

| Symptom | Likely Cause | Resolution |
| --- | --- | --- |
| Images show broken links | Storage symlink missing | Run `php artisan storage:link` |
| Images fallback to remote URLs | Download job failed or not queued | Check `MEDIA_AUTO_DOWNLOAD=true` and worker is running |
| `listing-media:backfill` runs but nothing downloads | No media worker running | Start worker with `--queue=media` |
| Disk full warnings | Orphaned files accumulating | Run `php artisan listing-media:prune --force` |
| Permission denied writing files | Wrong disk permissions | Fix ownership: `chown -R www-data:www-data storage/app/public` |

## Scheduler Configuration

The application uses Laravel's task scheduler for automated maintenance. The following tasks are configured:

| Schedule | Command | Description |
| --- | --- | --- |
| Daily 2:00 AM | `listing-media:prune --force` | Remove orphaned media files from storage |
| Daily 2:15 AM | `listings:cleanup-deleted --force` | Clean up media for listings soft-deleted > 30 days |
| Weekly Sunday 3:00 AM | `listings:cleanup-deleted --days=90 --hard-delete --force` | Hard-delete listings soft-deleted > 90 days |

### Setup

Add the scheduler to your system crontab (production):

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

For Forge, enable the scheduler in the site's "Scheduler" tab.

### Viewing Scheduled Tasks

```bash
# List all scheduled tasks and next run times
php artisan schedule:list

# Run the scheduler manually (executes due tasks)
php artisan schedule:run

# Test a specific command without waiting for schedule
php artisan schedule:test
```

### Scheduler Logs

Scheduled task output is logged to `storage/logs/scheduled-tasks.log`. Monitor for errors:

```bash
tail -f storage/logs/scheduled-tasks.log
```

## Caching Strategy

The application uses a layered caching approach to optimize performance while maintaining data freshness.

### Cache Drivers

| Environment | Driver | Notes |
| --- | --- | --- |
| Development | `database` | Default driver, uses `cache` table |
| Testing | `array` | In-memory, cleared between tests |
| Production | `database` or `redis` | Switch to Redis for high-traffic sites |

To switch to Redis in production:
```env
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### Cached Data

| Key Pattern | TTL | Purpose |
| --- | --- | --- |
| `db.meta.tables.*` | 60s | Database table list on homepage |
| `db.meta.has_table.*` | 60s | Table existence checks |
| `idx.pos.listings.*` | 5min | Homepage IDX Power of Sale listings |
| `idx.import.*` | varies | Import job progress/status |
| `media.*.count` | varies | Media job success/failure counters |

### Cache Management Commands

```bash
# Clear all caches
php artisan cache:clear

# Clear specific caches via Tinker
php artisan tinker
>>> Cache::forget('idx.pos.listings.4');
>>> Cache::flush();  # Clears everything (use with caution)

# Clear config/route/view caches (deployment)
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild caches (production)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Performance Considerations

1. **Query Caching**: Expensive dashboard counts (total listings, average price) are computed fresh on each request. For high-traffic sites, consider caching these with 1-5 minute TTLs.

2. **Redis Promotion**: Move to Redis when:
   - Response times for cached pages exceed targets
   - Database connection pool is saturated
   - You need distributed cache across multiple app servers

3. **Stale-While-Revalidate**: The homepage uses short TTLs (60s) for metadata to balance freshness and performance. Adjust these in `HomeController` if needed.
