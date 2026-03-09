# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

- **Laravel 12** (PHP 8.4) — backend framework
- **MySQL 8.4** — database
- **Nginx + PHP-FPM** — web server
- All services run in Docker; the app is never run directly on the host.

## Docker Commands

```bash
# Start all services
docker compose up -d

# Stop all services
docker compose down

# Rebuild app image (after Dockerfile or composer changes)
docker compose build app

# Run artisan commands
docker compose exec app php artisan <command>

# Run composer commands
docker compose exec app composer <command>

# Open a shell in the app container
docker compose exec app bash
```

## TDD Workflow

This project uses Test Driven Development. Always write the test first, then the implementation.

1. Write a failing test (`php artisan test` should show RED)
2. Write the minimum code to make it pass (GREEN)
3. Refactor while keeping tests green (REFACTOR)
4. Commit after each meaningful RED→GREEN→REFACTOR cycle

Tests use **SQLite in-memory** (configured in `phpunit.xml`) — no Docker MySQL needed for tests. Each test run migrates a fresh in-memory database automatically via `RefreshDatabase` trait.

Use `Tests\Feature` for HTTP/endpoint tests and `Tests\Unit` for pure logic tests.

## Git Commit Convention

Use [Conventional Commits](https://www.conventionalcommits.org/):

```
feat: add user registration endpoint
fix: correct password validation rule
test: add missing auth middleware tests
refactor: extract order calculation to service
```

Commits should be small and focused — one logical change per commit.

## Common Artisan Commands

```bash
# Run migrations
docker compose exec app php artisan migrate

# Fresh migration with seeders
docker compose exec app php artisan migrate:fresh --seed

# Run tests
docker compose exec app php artisan test

# Run a single test file
docker compose exec app php artisan test tests/Feature/ExampleTest.php

# Run tests matching a filter
docker compose exec app php artisan test --filter=TestName

# Generate application key
docker compose exec app php artisan key:generate

# Clear all caches
docker compose exec app php artisan optimize:clear
```

## Access

- **App**: http://localhost:8000
- **MySQL**: localhost:3306 (user: `laravel`, password: `secret`, db: `laravel`)

## Architecture

Standard Laravel MVC structure:
- `app/Http/Controllers/` — request handlers
- `app/Models/` — Eloquent models
- `routes/web.php` / `routes/api.php` — route definitions
- `resources/views/` — Blade templates
- `database/migrations/` — schema migrations
- `database/seeders/` — data seeders

## Docker Infrastructure

- `docker/php/Dockerfile` — PHP 8.4-FPM image with required extensions
- `docker/nginx/default.conf` — Nginx config routing to PHP-FPM on port 9000
- `docker-compose.yml` — defines `app`, `nginx`, and `mysql` services; MySQL data persisted in `mysql_data` volume

## Queue Worker & Scheduler

Jobs are stored in the database (`QUEUE_CONNECTION=database`). To process them:

```bash
# Start queue worker
docker compose exec app php artisan queue:work

# Run scheduler (processes every-minute jobs like FetchAllStockPrices)
docker compose exec app php artisan schedule:work
```

Stock prices are fetched from **Yahoo Finance** (no API key required). The scheduler runs `FetchAllStockPrices` every minute.

## Environment

The `.env` file is pre-configured for Docker with `DB_HOST=mysql` (the Docker service name). Do not use `127.0.0.1` for DB_HOST inside containers.

## Frontend Stack

- **Vue 3** (Composition API) with `<script setup>` — all views use `ref`, `computed`, `watchEffect`, `watch`, `nextTick`
- **Tailwind CSS v4** — utility-first styling; use `h-9` for all form inputs/selects for consistent height
- **Chart.js** — tree-shaken imports; register only the controllers/elements/plugins you use
- **Vite** — asset bundler; run via `npm run dev` or `npm run build` (inside the container or on host)

Key frontend conventions:
- Form error paragraphs use `h-[1.1rem]` (fixed height, no `mt-1`) to prevent layout shift
- Number inputs suppress spinners via global CSS in `resources/css/app.css`
- Dark mode is toggled via `document.documentElement.classList` — charts must re-render on toggle
- All chart scales set `border.color` and `grid.color` from a `chartColors()` helper that reads dark mode at render time

## Architecture (additional)

- `resources/js/views/` — Vue page components (one per route)
- `resources/js/components/` — shared Vue components
- `resources/js/composables/` — shared Composition API logic (e.g. `useTheme`)
- `app/Services/StockPriceService.php` — Yahoo Finance fetching with `.TW` → `.TWO` OTC fallback
- `app/Jobs/` — queued jobs (e.g. `FetchHistoricalPrices`, `FetchAllStockPrices`)

## Taiwan Market Rules

- All trading day calculations use **Asia/Taipei (UTC+8)**
- Price history is capped at **yesterday** (Taiwan time) — today's intraday data is excluded
- Handling fee: `max(20, floor(tradeValue × 0.1425% × (1 − discount)))` — buy and sell
- Transaction tax: `floor(tradeValue × 0.3%)` — sell only, 0 for buys
- Yahoo Finance: TWSE symbols use `.TW` suffix; OTC (TWO) symbols use `.TWO`; the service auto-retries with `.TWO` on 404

## Git Workflow

Commit after every meaningful feature or fix — do not batch unrelated changes into one commit. Follow the TDD cycle commit pattern:

```
test: add test for transaction update endpoint       ← RED
feat: add transaction update endpoint                ← GREEN
refactor: extract fee calculation to helper          ← REFACTOR (optional)
```

Additional commit type prefixes:
- `docs:` — documentation changes (e.g. API.md)
- `style:` — CSS/UI-only changes with no logic change
- `chore:` — dependency or config changes
