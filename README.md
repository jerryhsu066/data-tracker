# data-tracker

A personal stock portfolio tracker for Taiwan markets (TWSE / OTC), with support for US indices and other Yahoo Finance symbols. Tracks positions, cost basis, realized/unrealized gains, and market exposure across leveraged products.

---

## Features

- **Portfolio tracking** — Record buy/sell transactions; auto-calculates average cost, unrealized gain (net of estimated sell fees), and realized gain per position
- **Taiwan fee rules** — Handling fee: `max(NT$20, floor(trade value × 0.1425% × (1 − discount)))`; transaction tax: `floor(trade value × 0.3%)` on sells only; per-user discount setting
- **Market indices** — Track index symbols (e.g. `^TWII`, `^IXIC`, `^VIX`) for price history and charting without recording transactions
- **Portfolio value history** — Daily portfolio value and cost basis from earliest transaction to today, with carry-forward for missing price dates
- **Exposure bundles** — Group stocks with leverage multipliers to track effective market exposure separately from the main portfolio (useful for leveraged ETFs)
- **Cashflow tracking** — Log monthly income and expenses by customisable types and subtypes; monthly overview with an editable grid and a log view; import/export CSV or JSON
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

### 3. Run the init command

```bash
chmod +x data_tracker.sh
./data_tracker.sh init
```

This builds all images, generates the app key, runs migrations, and seeds the database in one step. It starts six containers:

| Container | Role |
|-----------|------|
| `laravel_app` | PHP-FPM application server |
| `laravel_nginx` | Nginx web server — app available at [http://localhost:8000](http://localhost:8000) |
| `laravel_mysql` | MySQL 8.4 database (port 3306) |
| `laravel_node` | Vite dev server — runs `npm install && npm run dev` (port 5173) |
| `laravel_worker` | Queue worker — processes background jobs (price fetches) |
| `laravel_scheduler` | Cron scheduler — dispatches the hourly weekday price sync |

The seeder inserts the three market index stocks (`^TWII`, `^IXIC`, `^VIX`) and dispatches a background job to fetch their last 30 days of price history.

### 4. Open the app

Go to [http://localhost:8000](http://localhost:8000) and register an account.

> **MySQL direct access** — host `127.0.0.1`, port `3306`, user `laravel`, password `secret`, database `laravel`

---

## Service Management

A helper script `data_tracker.sh` wraps all common operations. Make it executable once after cloning:

```bash
chmod +x data_tracker.sh
```

| Command | What it does |
|---------|-------------|
| `./data_tracker.sh init` | First-time setup: build images, copy `.env`, generate app key, run migrations & seed |
| `./data_tracker.sh start` | Start all services (detached) |
| `./data_tracker.sh restart` | Restart all services |
| `./data_tracker.sh stop` | Stop all services (data volumes are preserved) |
| `./data_tracker.sh update` | `git pull` → rebuild PHP images → restart changed containers → run new migrations → clear caches |
| `./data_tracker.sh logs [svc]` | Tail logs — all services, or one of: `app`, `nginx`, `worker`, `scheduler`, `mysql`, `node` |
| `./data_tracker.sh shell` | Open a bash shell in the app container |
| `./data_tracker.sh test [args]` | Run the test suite; pass extra args e.g. `--filter=SomeTest` |
| `./data_tracker.sh artisan <cmd>` | Run any Artisan command e.g. `./data_tracker.sh artisan migrate:fresh --seed` |

> **`update` does not require a manual stop.** `docker compose up -d` recreates only the containers whose image changed, so the service stays available during the rebuild.

### Raw Docker commands (if needed)

```bash
# Run all tests
docker compose exec app php artisan test

# Fresh migration (drops all tables and re-migrates)
docker compose exec app php artisan migrate:fresh --seed

# Tail worker logs
docker compose logs -f worker
```

---

## Application Structure

### Backend (`app/`)

```
app/
├── Http/Controllers/
│   ├── AuthController.php                  # Register, login, logout, me, update profile
│   ├── StockController.php                 # Stock CRUD, price fetch, sync history
│   ├── StockPriceHistoryController.php     # Daily price history for a stock
│   ├── StockTransactionController.php      # Buy/sell transaction CRUD
│   ├── StockImportExportController.php     # Import/export stock transactions (CSV/JSON)
│   ├── StockSettingsController.php         # Per-user handling fee discount
│   ├── PortfolioController.php             # Aggregated positions + value history
│   ├── ExposureBundleController.php        # Exposure bundle and entry management
│   ├── CashflowSettingsController.php      # Cashflow type and subtype CRUD
│   ├── CashflowRecordController.php        # Cashflow record CRUD + bulk operations
│   └── CashflowImportExportController.php  # Import/export cashflow records (CSV/JSON)
├── Models/
│   ├── User.php                    # Auth user; handling_fee_discount field
│   ├── Stock.php                   # Tracked symbol; current price + change %
│   ├── StockTransaction.php        # Buy/sell record; shares, price, fees
│   ├── StockPriceHistory.php       # Daily close price per stock
│   ├── ExposureBundle.php          # Named group of stocks for exposure calc
│   ├── ExposureBundleEntry.php     # Stock + leverage + optional shares override
│   ├── CashflowType.php            # User-defined cashflow category (income/expense)
│   ├── CashflowSubtype.php         # Sub-category under a cashflow type
│   └── CashflowRecord.php          # Monthly cashflow entry; amount + optional note
├── Actions/
│   └── SeedDefaultCashflowTypes.php  # Seeds default types/subtypes for new users
├── Services/
│   └── StockPriceService.php       # Yahoo Finance HTTP client; .TW → .TWO fallback
├── Jobs/
│   ├── FetchAllStockPrices.php     # Fans out one FetchStockPrice job per stock
│   ├── FetchStockPrice.php         # Fetches and stores current price for one stock
│   └── FetchHistoricalPrices.php   # Fetches daily closes from a given date to yesterday
└── Policies/
    └── StockTransactionPolicy.php  # Ownership checks: only the owner may update/delete
```

### Frontend (`resources/js/`)

```
resources/js/
├── views/
│   ├── HomeView.vue                # Overview: market indices + portfolio summary + exposure
│   ├── DashboardView.vue           # Portfolio: positions table + allocation/gain charts
│   ├── StocksView.vue              # Tracked stocks grid; add/delete stocks
│   ├── StockDetailView.vue         # Single stock: price chart + transaction history
│   ├── StockTransactionsView.vue   # All stock transactions across all stocks
│   ├── ExposureView.vue            # Exposure bundles: leverage-weighted market exposure
│   ├── SettingsView.vue            # Handling fee discount setting
│   ├── CashflowHomeView.vue        # Cashflow monthly overview grid (editable cells)
│   ├── CashflowLogView.vue         # Cashflow log: add/edit entries by type/subtype
│   ├── CashflowSettingsView.vue    # Manage cashflow types and subtypes
│   ├── UserSettingsView.vue        # Account settings (name, email, password, privacy lock)
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
PATCH  /api/auth/me                          # Update name / email / password
POST   /api/auth/verify-password

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

POST   /api/stocks/transactions              # Create stock transaction
PUT    /api/stocks/transactions/{id}         # Update stock transaction
DELETE /api/stocks/transactions/{id}         # Soft-delete stock transaction

GET    /api/stocks/settings                  # Get handling fee discount
PATCH  /api/stocks/settings                  # Update handling fee discount

GET    /api/stocks/export                    # Export transactions (CSV or JSON)
POST   /api/stocks/import/preview            # Preview import file
POST   /api/stocks/import                    # Import transactions

GET    /api/stocks/exposure/bundles                          # List exposure bundles
POST   /api/stocks/exposure/bundles                          # Create bundle
PATCH  /api/stocks/exposure/bundles/{id}                     # Rename / update cash
DELETE /api/stocks/exposure/bundles/{id}                     # Delete bundle
POST   /api/stocks/exposure/bundles/{id}/entries             # Add entry
PATCH  /api/stocks/exposure/bundles/{id}/entries/{eid}       # Update entry
DELETE /api/stocks/exposure/bundles/{id}/entries/{eid}       # Remove entry

GET    /api/cashflow/settings/types                          # List types with subtypes
POST   /api/cashflow/settings/types                          # Create type
PATCH  /api/cashflow/settings/types/{id}                     # Update type
DELETE /api/cashflow/settings/types/{id}                     # Delete type
POST   /api/cashflow/settings/types/{id}/subtypes            # Create subtype
PATCH  /api/cashflow/settings/subtypes/{id}                  # Update subtype
DELETE /api/cashflow/settings/subtypes/{id}                  # Delete subtype

GET    /api/cashflow/records                 # List records (filter by year/month)
POST   /api/cashflow/records                 # Create record
PATCH  /api/cashflow/records/{id}            # Update record
DELETE /api/cashflow/records/{id}            # Soft-delete record
POST   /api/cashflow/records/bulk            # Bulk create / update / delete

GET    /api/cashflow/export                  # Export cashflow records (CSV or JSON)
POST   /api/cashflow/import/preview          # Preview cashflow import file
POST   /api/cashflow/import                  # Import cashflow records
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
