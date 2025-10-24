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
   php artisan migrate
   ```
5. **Seed reference data (optional)**
   ```bash
   php artisan db:seed
   ```

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
| Run Vite dev server | `npm run dev` |
| Queue worker (database driver) | `php artisan queue:work` |
| Run scheduled tasks manually | `php artisan schedule:run` |
| Reset database | `php artisan migrate:fresh --seed` |

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
