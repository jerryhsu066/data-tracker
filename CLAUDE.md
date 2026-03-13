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
- `routes/api.php` — all API routes; auth under `/auth/*`, stocks under `/stocks/*`, cashflow under `/cashflow/*`
- `database/migrations/` — schema migrations
- `database/seeders/` — data seeders

### API Route Structure

Two top-level prefixes: `/stocks` for the stock/portfolio module, `/cashflow` for the cashflow module. Static paths are declared before `{symbol}` wildcard routes to avoid conflicts.

```
/auth/*               — register, login, logout, me, update profile

/stocks               — list / create stocks
/stocks/portfolio     — portfolio positions & value history
/stocks/transactions  — list all / create / update / delete stock transactions
/stocks/settings      — per-user handling fee discount
/stocks/export        — export transactions (CSV or JSON)
/stocks/import        — import transactions (with /preview)
/stocks/exposure/*    — exposure bundles & entries
/stocks/sync-history  — bulk price history sync
/stocks/{symbol}      — single stock CRUD & price fetch
/stocks/{symbol}/prices       — price history
/stocks/{symbol}/transactions — per-stock transactions

/cashflow/settings/types        — cashflow type CRUD
/cashflow/settings/types/{id}/subtypes — subtype management
/cashflow/settings/subtypes/{id}       — subtype update/delete
/cashflow/records     — cashflow record CRUD & bulk operations
/cashflow/export      — export cashflow records (CSV or JSON)
/cashflow/import      — import cashflow records (with /preview)
```

## Docker Infrastructure

- `docker/php/Dockerfile` — PHP 8.4-FPM image with required extensions
- `docker/nginx/default.conf` — Nginx config routing to PHP-FPM on port 9000
- `docker-compose.yml` — defines `app`, `nginx`, `mysql`, `worker`, `scheduler`, and `node` services; MySQL data persisted in `mysql_data` volume

## Queue Worker & Scheduler

Jobs are stored in the database (`QUEUE_CONNECTION=database`). Both the queue worker and scheduler run automatically as dedicated Docker services — no manual commands needed after `docker compose up -d`.

- **`worker`** container runs `php artisan queue:work` — processes queued jobs (e.g. `FetchStockPrice`)
- **`scheduler`** container runs `php artisan schedule:work` — dispatches `FetchAllStockPrices` **every hour on weekdays**

Stock prices are fetched from **Yahoo Finance** (no API key required). `FetchAllStockPrices` fans out by dispatching one `FetchStockPrice` job per tracked stock onto the queue.

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
- `resources/js/stores/` — shared state composables: `useTheme` (dark mode), `usePrivacy` (hide/show amounts), `useAuth`
- `app/Services/StockPriceService.php` — Yahoo Finance fetching with `.TW` → `.TWO` OTC fallback
- `app/Jobs/` — queued jobs (`FetchAllStockPrices` dispatches per-stock `FetchStockPrice` jobs)
- `app/Actions/SeedDefaultCashflowTypes.php` — seeds default cashflow types/subtypes for new users on registration
- `app/Policies/StockTransactionPolicy.php` — ownership gate for stock transaction update/delete
- `app/Policies/ExposureBundlePolicy.php` — ownership gate for exposure bundle CRUD
- `app/Http/Controllers/Concerns/ParsesImportFile.php` — shared CSV/JSON parsing trait for import controllers

### Key naming conventions

Models, controllers, tables, and columns are prefixed with their module to avoid ambiguity:

| Module | Table | Model | Controller |
|--------|-------|-------|------------|
| Stocks | `stock_transactions` | `StockTransaction` | `StockTransactionController` |
| Stocks | `stocks` | `Stock` | `StockController` |
| Cashflow | `cashflow_records` | `CashflowRecord` | `CashflowRecordController` |
| Cashflow | `cashflow_types` | `CashflowType` | `CashflowSettingsController` |
| Cashflow | `cashflow_subtypes` | `CashflowSubtype` | `CashflowSettingsController` |

Cashflow foreign key columns: `cashflow_type_id`, `cashflow_subtype_id` (not `type_id` / `subtype_id`).

## Taiwan Market Rules

- App timezone is set to **Asia/Taipei (UTC+8)** in `config/app.php` — all date/time functions default to Taiwan time
- Price history includes **today** — `StockPriceHistoryController` and `PortfolioController` cap at today, not yesterday
- Missing price history dates use **carry-forward** — last known price on or before that date is used
- Handling fee: `max(20, floor(tradeValue × 0.1425% × (1 − discount)))` — buy and sell
- Transaction tax: `floor(tradeValue × 0.3%)` — sell only, 0 for buys
- Yahoo Finance: TWSE symbols use `.TW` suffix; OTC (TWO) symbols use `.TWO`; the service auto-retries with `.TWO` on 404
- Market index symbols (`^TWII`, `^IXIC`, `^VIX`) are supported for price tracking — symbol regex allows `^` prefix

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
