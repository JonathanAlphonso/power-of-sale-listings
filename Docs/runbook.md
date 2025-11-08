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

1. Provision server with PHP 8.3, Node 20, MySQL 8.
2. Point Forge site to repository and target branch.
3. Set environment variables (`APP_ENV=production`, `APP_DEBUG=false`, DB credentials, mail driver, queue driver).
4. Deploy script sequence:
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   npm ci
   npm run build
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
5. Link storage (`php artisan storage:link`) if not already configured.
6. Start queue worker (`php artisan queue:work --sleep=3 --tries=3` under Forge daemon).
7. Add scheduler (`* * * * * php artisan schedule:run`).
8. Enable HTTPS via Let’s Encrypt and verify `APP_URL`.

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

If you see “Import queued” on `/admin/feeds` but nothing happens, a worker is not running.
