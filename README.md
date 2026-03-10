# data-tracker

A personal stock portfolio tracker for Taiwan markets (TWSE / OTC), with support for US indices and other Yahoo Finance symbols. Tracks positions, cost basis, realized/unrealized gains, and market exposure across leveraged products.

---

## Features

- **Portfolio tracking** — Record buy/sell transactions; auto-calculates average cost, unrealized gain (net of estimated sell fees), and realized gain per position
- **Taiwan fee rules** — Handling fee: `max(NT$20, floor(trade value × 0.1425% × (1 − discount)))`; transaction tax: `floor(trade value × 0.3%)` on sells only; per-user discount setting
- **Market indices** — Track index symbols (e.g. `^TWII`, `^IXIC`, `^VIX`) for price history and charting without recording transactions
- **Portfolio value history** — Daily portfolio value and cost basis from earliest transaction to today, with carry-forward for missing price dates
- **Exposure bundles** — Group stocks with leverage multipliers to track effective market exposure separately from the main portfolio (useful for leveraged ETFs)
- **Price data** — Fetched from Yahoo Finance (no API key required); TWSE symbols use `.TW`, OTC symbols use `.TWO`; auto-retries `.TW` → `.TWO` on 404
- **Scheduled price sync** — Prices fetched hourly on weekdays via a background queue worker; manual sync also available
- **Privacy mode** — Eye icon in the navbar toggles visibility of portfolio-sensitive amounts (prices are always shown as public market data)
- **Dark mode** — Persisted across sessions
- **Responsive** — Works on mobile and desktop

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12, PHP 8.4 |
| Database | MySQL 8.4 |
| Web server | Nginx + PHP-FPM |
| Auth | Laravel Sanctum (token-based) |
| Queue | Database queue driver |
| Frontend | Vue 3 (Composition API, `<script setup>`) |
| Styling | Tailwind CSS v4 |
| Charts | Chart.js |
| Bundler | Vite |
| Infrastructure | Docker Compose |

---

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (or Docker Engine + Compose plugin)
- Git

No PHP, Node, or Composer installation required on the host — everything runs inside Docker.

---

## Initial Setup

### 1. Clone the repository

```bash
git clone <repo-url> data-tracker
cd data-tracker
```

### 2. Create the environment file

```bash
cp .env.example .env
```

The default `.env.example` is pre-configured for Docker (MySQL host is `mysql`, matching the Docker Compose service name). No changes are required for local development.

### 3. Build and start all services

```bash
docker compose up -d --build
```

This starts six containers:

| Container | Role |
|-----------|------|
| `laravel_app` | PHP-FPM application server |
| `laravel_nginx` | Nginx web server — app available at [http://localhost:8000](http://localhost:8000) |
| `laravel_mysql` | MySQL 8.4 database (port 3306) |
| `laravel_node` | Vite dev server — runs `npm install && npm run dev` (port 5173) |
| `laravel_worker` | Queue worker — processes background jobs (price fetches) |
| `laravel_scheduler` | Cron scheduler — dispatches the hourly weekday price sync |

### 4. Install PHP dependencies and generate the app key

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
```

### 5. Run database migrations and seed initial data

```bash
docker compose exec app php artisan migrate --seed
```

This migrates the schema and runs the seeder, which inserts the three market index stocks (`^TWII`, `^IXIC`, `^VIX`) and dispatches a background job to fetch their last 30 days of price history. The worker container processes the job automatically.

### 6. Open the app

Go to [http://localhost:8000](http://localhost:8000) and register an account.

> **MySQL direct access** — host `127.0.0.1`, port `3306`, user `laravel`, password `secret`, database `laravel`

---

## Common Commands

```bash
# Start all services
docker compose up -d

# Stop all services
docker compose down

# Rebuild the PHP image (after Dockerfile or composer.json changes)
docker compose build app

# Run all tests
docker compose exec app php artisan test

# Run a specific test file
docker compose exec app php artisan test tests/Feature/TransactionApiTest.php

# Run tests matching a name
docker compose exec app php artisan test --filter=test_can_record_a_buy_transaction

# Fresh migration (drops all tables and re-migrates)
docker compose exec app php artisan migrate:fresh

# Open a shell in the app container
docker compose exec app bash

# Tail application logs
docker compose logs -f app

# Tail worker logs
docker compose logs -f worker
```

---

## Application Structure

### Backend (`app/`)

```
app/
├── Http/Controllers/
│   ├── AuthController.php               # Register, login, logout, me
│   ├── StockController.php              # Stock CRUD, price fetch, sync history
│   ├── StockPriceHistoryController.php  # Daily price history for a stock
│   ├── TransactionController.php        # Buy/sell transaction CRUD
│   ├── PortfolioController.php          # Aggregated positions + value history
│   ├── SettingsController.php           # Per-user handling fee discount
│   └── ExposureBundleController.php     # Exposure bundle and entry management
├── Models/
│   ├── User.php                    # Auth user; handling_fee_discount field
│   ├── Stock.php                   # Tracked symbol; current price + change %
│   ├── Transaction.php             # Buy/sell record; shares, price, fees
│   ├── StockPriceHistory.php       # Daily close price per stock
│   ├── ExposureBundle.php          # Named group of stocks for exposure calc
│   └── ExposureBundleEntry.php     # Stock + leverage + optional shares override
├── Services/
│   └── StockPriceService.php       # Yahoo Finance HTTP client; .TW → .TWO fallback
├── Jobs/
│   ├── FetchAllStockPrices.php     # Fans out one FetchStockPrice job per stock
│   ├── FetchStockPrice.php         # Fetches and stores current price for one stock
│   └── FetchHistoricalPrices.php   # Fetches daily closes from a given date to yesterday
└── Policies/
    └── TransactionPolicy.php       # Ownership checks: only the owner may update/delete
```

### Frontend (`resources/js/`)

```
resources/js/
├── views/
│   ├── HomeView.vue           # Overview: market indices + portfolio summary + exposure
│   ├── DashboardView.vue      # Portfolio: positions table + allocation/gain charts
│   ├── StocksView.vue         # Tracked stocks grid; add/delete stocks
│   ├── StockDetailView.vue    # Single stock: price chart + transaction history
│   ├── TransactionsView.vue   # All transactions across all stocks
│   ├── ExposureView.vue       # Exposure bundles: leverage-weighted market exposure
│   ├── SettingsView.vue       # Handling fee discount setting
│   ├── LoginView.vue
│   └── RegisterView.vue
├── components/
│   └── NavBar.vue             # Fixed top bar with hamburger menu (mobile), theme/privacy toggles
└── stores/
    ├── auth.js                # Singleton auth state (user, token)
    ├── theme.js               # Dark mode toggle; persisted in localStorage
    └── privacy.js             # Privacy mode toggle; persisted in localStorage
```

### Routes

All stock-module routes share the `/stocks` prefix. Static paths are declared before `{symbol}` wildcard routes to avoid conflicts.

```
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me

GET    /api/stocks                           # List all tracked stocks (public)
POST   /api/stocks                           # Add a stock
GET    /api/stocks/{symbol}                  # Get single stock (public)
DELETE /api/stocks/{symbol}                  # Soft-delete a stock
POST   /api/stocks/{symbol}/fetch            # Refresh price from Yahoo Finance
GET    /api/stocks/{symbol}/prices           # Price history (public)
GET    /api/stocks/{symbol}/transactions     # User's transactions for a stock

POST   /api/stocks/sync-history              # Dispatch historical price fetch for all stocks
GET    /api/stocks/portfolio                 # Aggregated positions
GET    /api/stocks/portfolio/history         # Daily portfolio value + cost basis

POST   /api/stocks/transactions              # Create transaction
PUT    /api/stocks/transactions/{id}         # Update transaction
DELETE /api/stocks/transactions/{id}         # Soft-delete transaction

GET    /api/stocks/settings                  # Get handling fee discount
PATCH  /api/stocks/settings                  # Update handling fee discount

GET    /api/stocks/exposure/bundles                          # List exposure bundles
POST   /api/stocks/exposure/bundles                          # Create bundle
PATCH  /api/stocks/exposure/bundles/{id}                     # Rename / update cash
DELETE /api/stocks/exposure/bundles/{id}                     # Delete bundle
POST   /api/stocks/exposure/bundles/{id}/entries             # Add entry
PATCH  /api/stocks/exposure/bundles/{id}/entries/{eid}       # Update entry
DELETE /api/stocks/exposure/bundles/{id}/entries/{eid}       # Remove entry
```

See [`API.md`](./API.md) for the full request/response reference.

---

## Background Jobs

Jobs are stored in the database and processed by the `worker` container automatically after `docker compose up -d`.

| Job | Triggered by | What it does |
|-----|-------------|--------------|
| `FetchAllStockPrices` | Scheduler — hourly, weekdays | Dispatches one `FetchStockPrice` job per tracked stock |
| `FetchStockPrice` | `FetchAllStockPrices` | Fetches current price from Yahoo Finance; updates `current_price`, `change_percent`, and today's price history record |
| `FetchHistoricalPrices` | Transaction create/update; `POST /stocks/sync-history` | Fetches daily closes from a start date to yesterday and upserts price history records |

---

## Development Workflow

This project uses **Test Driven Development**. Tests use an SQLite in-memory database — no running containers needed to run the test suite.

```bash
# Write a failing test, run it (RED)
docker compose exec app php artisan test --filter=YourNewTest

# Implement the feature, run again (GREEN)
docker compose exec app php artisan test

# Refactor, confirm still green
docker compose exec app php artisan test
```

Commit convention follows [Conventional Commits](https://www.conventionalcommits.org/):

```
feat: add portfolio value history endpoint
fix: carry forward price on missing history dates
test: add sync-history dispatch tests
refactor: replace O(n²) price scan with cursor walk
perf: reduce netSharesForUser to single query
docs: update API.md with exposure bundle routes
style: adjust chart grid colors for dark mode
chore: upgrade laravel/sanctum to 4.3
```

---

## Taiwan Market Notes

- All trading day calculations use **Asia/Taipei (UTC+8)**
- **Handling fee**: `max(NT$20, floor(trade_value × 0.1425% × (1 − discount)))` — applied to both buys and sells
- **Transaction tax**: `floor(trade_value × 0.3%)` — sells only; 0 for buys
- **TWSE** symbols use `.TW` suffix (e.g. `2330.TW`); **OTC** symbols use `.TWO` (e.g. `00631L.TWO`)
- The price service automatically retries `.TW` symbols with `.TWO` on a 404 from Yahoo Finance
- Market index symbols (`^TWII`, `^IXIC`, `^VIX`) are tracked for price data only — transactions cannot be recorded against them
