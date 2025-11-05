# Power of Sale Listing Platform

A Laravel 12 + Livewire stack for aggregating and managing Ontario power of sale listings. The application ships with Volt single-file components, Flux UI for the interface layer, Tailwind CSS v4, and a MySQL-first data model tailored for ingestion pipelines, admin review flows, and a future public portal.

## Key Technologies

- **Backend:** Laravel 12, Fortify authentication, Livewire 3, Volt, Flux UI Free
- **Frontend:** Vite 7, Tailwind CSS 4, Alpine (bundled via Livewire)
- **Database:** MySQL 8 (queues, cache, and sessions use database drivers out of the box)
- **Tooling:** PHP 8.3, Composer 2, Node.js 20, npm 11, Pest, Laravel Pint

## Prerequisites

Ensure the following tooling is available locally before installing dependencies:

| Tool | Version |
| --- | --- |
| PHP | 8.3.26 or newer |
| Composer | 2.x |
| Node.js | 20.19.x (or newer LTS) |
| npm | 11.x |
| MySQL | 8.0.x |

> Tip: When using WSL2 with Herd managing PHP on Windows, keep Node/Vite in WSL. Vite is already configured with polling (`watch.usePolling=true`) so file changes under `/mnt/c` are detected reliably.

## Initial Setup

1. **Install PHP & Node dependencies**
   ```bash
   composer install
   npm install
   ```
2. **Bootstrap environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
3. **Configure MySQL credentials**  
   Update the `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` entries in `.env` to match your local MySQL instance. When PHP runs through Herd on Windows, keep the host as `127.0.0.1` so WSL bridges over TCP.
4. **Provision the database**
   ```bash
   mysql -u <user> -p -e "CREATE DATABASE IF NOT EXISTS <database> CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   php artisan migrate --graceful
   ```
   Re-run the migration command any time new migrations land; the `--graceful` flag prevents non-zero exits when no changes are pending.
5. **Seed reference data (optional but recommended)**
   ```bash
   php artisan db:seed --class=Database\Seeders\DatabaseSeeder
   ```
   The seeder provisions demo accounts and listings that align with the admin workspace:

   | Account | Email | Role | Password |
   | --- | --- | --- | --- |
   | Administrator | `admin@powerofsale.test` | Admin | `password` |
   | Analyst | `analyst@powerofsale.test` | Subscriber | `password` |

### Admin Workspace URLs

Once seeded, sign in as the administrator to reach the secured workspace. These routes all require the `Admin` role:

- `/dashboard` — analytics snapshot and recent listings/users.
- `/admin/listings` — manage listings, filters, and suppression workflow.
- `/admin/users` — invite, suspend, and manage internal users.
- `/admin/settings/analytics` — configure Google Analytics credentials.

## Local Development

### PHP Application

- **Herd users (recommended):** Serve the project via Herd on Windows. Point the Herd site to the repository root and ensure `.env` uses the same domain as `APP_URL`.  
- **Ad-hoc server:** If you prefer the built-in server, run `php artisan serve`.  
- **WSL caveat:** Binding from WSL may fail if Herd already occupies the port. Use Herd for PHP and run Vite from WSL for the smoothest workflow.

### Vite Dev Server

```bash
npm run dev
```

The configuration locks the port to `5173` (`strictPort=true`) and uses HMR over `localhost`. Update your `.env` with `APP_URL` that matches the Herd domain to avoid mixed-content or cookie issues.

### Queues & Scheduled Jobs

The project defaults to the database queue driver; queue tables are already generated in `database/migrations`. You can run workers during development with:

```bash
php artisan queue:work
```

## Common Artisan Commands

| Task | Command |
| --- | --- |
| Apply pending migrations | `php artisan migrate --graceful` |
| Reseed demo data without dropping tables | `php artisan db:seed --class=Database\Seeders\DatabaseSeeder` |
| Reset schema and reseed listings | `php artisan migrate:fresh --seed` |

## Build & Quality Checks

| Task | Command |
| --- | --- |
| Format dirty files | `vendor/bin/pint --dirty` |
| Run tests | `composer test` |
| Build production assets | `npm run build` |

GitHub Actions workflows (`.github/workflows/`) enforce these steps on `develop` and `main` branches:

- **tests.yml:** installs dependencies, builds assets, and runs `composer test`.
- **lint.yml:** installs dependencies and runs `vendor/bin/pint --dirty`.

## Troubleshooting

Quick fixes for the most common issues. For a deeper playbook covering deployment and CI, refer to [`docs/runbook.md`](docs/runbook.md).

- **Vite cannot connect:** Confirm Vite is running in WSL and your browser points to `http://localhost:5173`. If the port is in use, stop the conflicting process or adjust the port in `vite.config.js`.
- **Database authentication errors:** Re-check credentials in `.env` and ensure MySQL accepts TCP connections on 127.0.0.1. In mixed Windows/WSL environments, confirm that Herd’s MySQL service is reachable from WSL.
- **File watching delays:** Because the repository lives under `/mnt/c`, Vite polls for changes. This is intentional; removing the polling option may cause missed updates.

## Project Roadmap & Docs

- `Docs/task-list.md` — milestone checklist for foundation, data model, ingestion, and public portal work.
- `Docs/build-plan.md` — detailed milestone plan, technology choices, and cross-cutting concerns.
- `docs/runbook.md` — complete setup workflow, deployment checklist, and troubleshooting matrix.
- `Docs/proptx-api-mapping.md` — PropTx/IDX fields requested, `$select` guidance, and mapping to the `listings` schema.

## Contributing

1. Create a feature branch from `develop`.
2. Follow the conventions outlined in `Docs/task-list.md` and existing Livewire/Volt patterns.
3. Run `composer test`, `vendor/bin/pint --dirty`, and `npm run build` (if assets change) before opening a pull request.
4. Submit the PR against `develop` with a concise summary of the changes, risks, and testing evidence.

## License

This project inherits the license of the Laravel Livewire starter kit (MIT). Review the repository’s `LICENSE` file for details.
