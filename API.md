# API Documentation

Base URL: `http://localhost:8000/api`

All protected endpoints require a Bearer token in the `Authorization` header:
```
Authorization: Bearer <token>
```

---

## Authentication

### Register
```
POST /auth/register
```
**Body**
| Field | Type | Rules |
|---|---|---|
| `name` | string | required, max 255 |
| `email` | string | required, valid email, unique |
| `password` | string | required, min 8 |
| `password_confirmation` | string | required, must match `password` |

**Response `201`**
```json
{
  "token": "1|abc...",
  "user": {
    "id": 1,
    "name": "Jerry",
    "email": "jerry@example.com",
    "handling_fee_discount": 0,
    "created_at": "2026-01-30T00:00:00.000000Z",
    "updated_at": "2026-01-30T00:00:00.000000Z"
  }
}
```

---

### Login
```
POST /auth/login
```
**Body**
| Field | Type | Rules |
|---|---|---|
| `email` | string | required |
| `password` | string | required |

**Response `200`**
```json
{
  "token": "2|xyz...",
  "user": { ... }
}
```

**Response `401`** — invalid credentials

---

### Get Current User
```
GET /auth/me
```
🔒 Requires auth.

**Response `200`**
```json
{
  "id": 1,
  "name": "Jerry",
  "email": "jerry@example.com",
  "handling_fee_discount": 0.3
}
```

---

### Logout
```
POST /auth/logout
```
🔒 Requires auth. Revokes the current token.

**Response `200`**
```json
{ "message": "Logged out successfully." }
```

---

## Stocks

### List All Stocks
```
GET /stocks
```
Public. Returns all tracked stocks ordered by symbol.

**Response `200`**
```json
[
  {
    "id": 1,
    "symbol": "0050.TW",
    "name": "元大台灣50",
    "current_price": "73.6000",
    "change_percent": "1.2400",
    "last_fetched_at": "2026-03-10T06:30:00.000000Z"
  }
]
```

---

### Get Single Stock
```
GET /stocks/{symbol}
```
Public.

**Response `200`** — stock object (same shape as above)

**Response `404`** — symbol not found

---

### Add Stock
```
POST /stocks
```
🔒 Requires auth.

**Body**
| Field | Type | Rules |
|---|---|---|
| `symbol` | string | required, max 15, matches `/^\^?[A-Z0-9]+(\.[A-Z]+)?$/` |
| `name` | string | required, max 255 |

Symbol is automatically uppercased. The `^` prefix is allowed for market indices (e.g. `^TWII`, `^IXIC`, `^VIX`). TWSE symbols use `.TW` suffix; OTC symbols use `.TWO`.

**Response `201`** — created stock object

**Response `422`** — validation errors (e.g. symbol already exists)

---

### Delete Stock
```
DELETE /stocks/{symbol}
```
🔒 Requires auth. Soft-deletes the stock.

**Response `204`** — no content

---

### Refresh Stock Price
```
POST /stocks/{symbol}/fetch
```
🔒 Requires auth. Fetches the latest price from Yahoo Finance synchronously and records today's closing price. `last_fetched_at` is set to the `regularMarketTime` from Yahoo Finance (i.e. when the price data is from, not when the fetch was triggered).

For `.TW` symbols not found on Yahoo Finance, automatically retries with `.TWO` (Taiwan OTC market).

**Response `200`** — updated stock object

---

### Get Price History
```
GET /stocks/{symbol}/prices
```
Public. Returns daily closing prices up to and including today (Taiwan time), ordered by date ascending.

**Response `200`**
```json
[
  { "id": 1, "stock_id": 1, "date": "2026-01-30", "close_price": "72.6000" },
  { "id": 2, "stock_id": 1, "date": "2026-03-10", "close_price": "73.6000" }
]
```

---

### Sync Price History
```
POST /stocks/sync-history
```
🔒 Requires auth. Fetches daily closing prices for **all** tracked stocks from `from_date` up to yesterday (Taiwan time). Runs synchronously.

**Body**
| Field | Type | Rules |
|---|---|---|
| `from_date` | date | required, on or before today |

**Response `200`**
```json
{ "synced": 5 }
```

`synced` is the number of stocks processed.

---

### Get Transactions for a Stock
```
GET /stocks/{symbol}/transactions
```
🔒 Requires auth. Returns the authenticated user's transactions for the given stock, ordered by date descending.

**Response `200`**
```json
[
  {
    "id": 1,
    "user_id": 1,
    "stock_id": 1,
    "type": "buy",
    "shares": "1000.0000",
    "price_per_share": "72.7500",
    "handling_fee": "103",
    "transaction_tax": "0",
    "transacted_at": "2026-01-30",
    "notes": null,
    "stock": { "id": 1, "symbol": "0050.TW", "name": "元大台灣50", ... }
  }
]
```

---

## Stock Transactions

### Create Stock Transaction
```
POST /stocks/transactions
```
🔒 Requires auth.

**Body**
| Field | Type | Rules |
|---|---|---|
| `stock_id` | integer | required, must exist |
| `type` | string | required, `buy` or `sell` |
| `shares` | number | required, > 0 |
| `price_per_share` | number | required, > 0 |
| `handling_fee` | number | optional, ≥ 0 (auto-calculated if omitted) |
| `transaction_tax` | number | optional, ≥ 0 (auto-calculated if omitted) |
| `transacted_at` | date | required |
| `notes` | string | optional |

Fee and tax are auto-calculated from the user's `handling_fee_discount` setting if not supplied:
- **Handling fee** = `max(20, floor(tradeValue × 0.1425% × (1 − discount)))`
- **Transaction tax** = `floor(tradeValue × 0.3%)` for sells; `0` for buys

For sell transactions, validates that shares owned ≥ shares to sell.

**Response `201`** — created transaction object with `stock` relationship

**Response `422`** — validation error or insufficient shares

---

### Update Stock Transaction
```
PUT /stocks/transactions/{id}
```
🔒 Requires auth. Only the transaction owner may update.

**Body**
| Field | Type | Rules |
|---|---|---|
| `type` | string | required, `buy` or `sell` |
| `shares` | number | required, > 0 |
| `price_per_share` | number | required, > 0 |
| `handling_fee` | number | required, ≥ 0 |
| `transaction_tax` | number | required, ≥ 0 |
| `transacted_at` | date | required |
| `notes` | string | optional |

Fee and tax are accepted as-is (user-overridable).

**Response `200`** — updated transaction object with `stock` relationship

**Response `403`** — not the transaction owner

---

### Delete Stock Transaction
```
DELETE /stocks/transactions/{id}
```
🔒 Requires auth. Only the transaction owner may delete. Soft-deletes the record.

**Response `204`** — no content

**Response `403`** — not the transaction owner

---

## Stock Import / Export

### Export Transactions
```
GET /stocks/export?format=csv|json
```
🔒 Requires auth. Returns the authenticated user's transactions in the requested format. If the user has no transactions, a single example row is included.

**Response `200`** — streamed CSV (`text/csv`) or JSON array (`application/json`)

CSV columns: `date, symbol, type, shares, price_per_share, handling_fee, transaction_tax, notes`

---

### Preview Import
```
POST /stocks/import/preview
```
🔒 Requires auth. Validates and classifies rows without writing to the database. Unknown stock symbols are looked up on Yahoo Finance.

**Body (multipart/form-data)**
| Field | Type | Rules |
|---|---|---|
| `file` | file | required, CSV or JSON |
| `format` | string | required, `csv` or `json` |

**Response `200`**
```json
{
  "total": 3,
  "valid": 1,
  "invalid": [
    { "row": 2, "reason": "Invalid date format" }
  ],
  "duplicates": [
    { "row": 3, "reason": "Duplicate transaction" }
  ]
}
```

---

### Import Transactions
```
POST /stocks/import
```
🔒 Requires auth. Imports valid, non-duplicate rows. Unknown stock symbols are created if found on Yahoo Finance; skipped otherwise.

**Body (multipart/form-data)**
| Field | Type | Rules |
|---|---|---|
| `file` | file | required, CSV or JSON |
| `format` | string | required, `csv` or `json` |
| `skip_duplicates` | boolean | optional, default `true` |

**Response `200`**
```json
{
  "imported": 2,
  "skipped": [
    { "row": 3, "reason": "Duplicate transaction" }
  ]
}
```

---

## Portfolio

### Get Portfolio Positions
```
GET /stocks/portfolio
```
🔒 Requires auth. Returns current positions for all stocks the user has transactions in (only stocks with `net_shares > 0`).

**Response `200`**
```json
[
  {
    "stock": {
      "id": 1,
      "symbol": "0050.TW",
      "name": "元大台灣50",
      "current_price": "73.6000",
      "change_percent": "1.2400",
      "last_fetched_at": "2026-03-10T06:30:00.000000Z"
    },
    "net_shares": "1380.0000",
    "average_cost": "72.8804",
    "current_value": "101568.0000",
    "unrealized_gain": "545.1200",
    "realized_gain": "0.0000"
  }
]
```

**Field notes**
| Field | Description |
|---|---|
| `average_cost` | Total buy cost (including fees) ÷ total shares bought |
| `current_value` | `net_shares × current_price` |
| `unrealized_gain` | `current_value − (average_cost × net_shares)` |
| `realized_gain` | Net proceeds from sells minus the average cost of shares sold |

---

### Get Portfolio Value History
```
GET /stocks/portfolio/history
```
🔒 Requires auth. Returns daily portfolio value and cost basis from the earliest transaction date up to today (Taiwan time).

For each date, only shares held at that date are included (future purchases are not counted back in time). Missing price data is carried forward from the most recent available price. Dates where total value is 0 are omitted.

**Response `200`**
```json
[
  {
    "date": "2026-01-30",
    "value": 418761.60,
    "cost_basis": 396283.15
  }
]
```

**Field notes**
| Field | Description |
|---|---|
| `value` | Sum of `net_shares_held_at_date × close_price` across all stocks |
| `cost_basis` | Sum of `average_cost_at_date × net_shares_held_at_date` across all stocks |

---

## Settings

### Get Settings
```
GET /stocks/settings
```
🔒 Requires auth.

**Response `200`**
```json
{ "handling_fee_discount": 0.3 }
```

`handling_fee_discount` is a decimal between `0` (no discount) and `1` (100% discount off the standard 0.1425% brokerage rate).

---

### Update Settings
```
PATCH /stocks/settings
```
🔒 Requires auth.

**Body**
| Field | Type | Rules |
|---|---|---|
| `handling_fee_discount` | number | required, 0 – 1 |

**Response `200`**
```json
{ "handling_fee_discount": 0.3 }
```

---

## Exposure Bundles

A bundle groups stocks with assigned leverages to track market exposure rate separately from the main portfolio.

### List Bundles
```
GET /stocks/exposure/bundles
```
🔒 Requires auth. Returns all bundles for the authenticated user, each with their entries and current stock prices.

**Response `200`**
```json
[
  {
    "id": 1,
    "name": "Core",
    "cash": "50000.00",
    "entries": [
      {
        "id": 1,
        "stock_id": 1,
        "net_shares": "1000.0000",
        "leverage": "2.0",
        "is_cash": false,
        "stock": { "id": 1, "symbol": "00631L.TW", "current_price": "18.50", ... }
      }
    ]
  }
]
```

---

### Create Bundle
```
POST /stocks/exposure/bundles
```
🔒 Requires auth.

**Body**
| Field | Type | Rules |
|---|---|---|
| `name` | string | required, max 255 |

**Response `201`** — created bundle object

---

### Update Bundle
```
PATCH /stocks/exposure/bundles/{bundle}
```
🔒 Requires auth. Owned bundles only. Used for renaming and updating cash balance.

**Body**
| Field | Type | Rules |
|---|---|---|
| `name` | string | optional, max 255 |
| `cash` | number | optional, ≥ 0 |

**Response `200`** — updated bundle object with entries

---

### Delete Bundle
```
DELETE /stocks/exposure/bundles/{bundle}
```
🔒 Requires auth. Owned bundles only.

**Response `204`** — no content

---

### Add Entry to Bundle
```
POST /stocks/exposure/bundles/{bundle}/entries
```
🔒 Requires auth.

**Body**
| Field | Type | Rules |
|---|---|---|
| `stock_id` | integer | required, must exist |
| `shares_override` | number | optional, ≥ 0 — if null, defaults to the user's net shares for that stock |
| `leverage` | number | optional, ≥ 0, default 1 |
| `is_cash` | boolean | optional, default false — marks the entry as a cash proxy (leverage is ignored) |

**Response `200`** — updated bundle object with entries

---

### Update Entry
```
PATCH /stocks/exposure/bundles/{bundle}/entries/{entry}
```
🔒 Requires auth.

**Body**
| Field | Type | Rules |
|---|---|---|
| `shares_override` | number | optional, ≥ 0 |
| `leverage` | number | optional, ≥ 0 |
| `is_cash` | boolean | optional |

**Response `200`** — updated bundle object with entries

---

### Remove Entry
```
DELETE /stocks/exposure/bundles/{bundle}/entries/{entry}
```
🔒 Requires auth.

**Response `200`** — updated bundle object with entries

---

---

## Cashflow Settings

On registration, default types (`Income`, `Credit Card`, `Housing`, `Subscription`) and subtypes are seeded automatically.

### List Types
```
GET /cashflow/settings/types
```
🔒 Requires auth. Returns the user's cashflow types with nested subtypes and an `unsubtyped_records_count` for each type.

**Response `200`**
```json
[
  {
    "id": 1,
    "name": "Credit Card",
    "is_expense": true,
    "is_disabled": false,
    "is_private": false,
    "merge_subtypes": false,
    "unsubtyped_records_count": 0,
    "subtypes": [
      { "id": 1, "name": "HSBC", "is_disabled": false, "is_private": false }
    ]
  }
]
```

---

### Create Type
```
POST /cashflow/settings/types
```
🔒 Requires auth.

**Body**
| Field | Type | Rules |
|---|---|---|
| `name` | string | required, max 255 |
| `is_expense` | boolean | required |

**Response `201`** — created type object

---

### Update Type
```
PATCH /cashflow/settings/types/{id}
```
🔒 Requires auth. Owned types only.

**Body** (all optional)
| Field | Type | Notes |
|---|---|---|
| `name` | string | max 255 |
| `is_disabled` | boolean | cascades `is_disabled` to all subtypes |
| `is_private` | boolean | cascades `is_private` to all subtypes |
| `merge_subtypes` | boolean | display subtypes as a single merged column |

**Response `200`** — updated type object

---

### Delete Type
```
DELETE /cashflow/settings/types/{id}
```
🔒 Requires auth. Owned types only. Fails with `422` if the type has existing records (disable instead).

**Response `204`** — no content

---

### Create Subtype
```
POST /cashflow/settings/types/{id}/subtypes
```
🔒 Requires auth. Owned types only.

**Body**
| Field | Type | Rules |
|---|---|---|
| `name` | string | required, max 255 |
| `migrate_existing` | boolean | optional — if `true` and this is the *first* subtype, reassigns all existing null-subtype records to this new subtype |

**Response `201`**
```json
{
  "subtype": { "id": 2, "name": "CTBC", "cashflow_type_id": 1, ... },
  "migrated_count": 3
}
```

---

### Update Subtype
```
PATCH /cashflow/settings/subtypes/{id}
```
🔒 Requires auth. Owned subtypes only.

**Body** (all optional)
| Field | Type |
|---|---|
| `name` | string |
| `is_disabled` | boolean |
| `is_private` | boolean |

**Response `200`** — updated subtype object

---

### Delete Subtype
```
DELETE /cashflow/settings/subtypes/{id}
```
🔒 Requires auth. Owned subtypes only. Fails with `422` if the subtype has existing records.

**Response `204`** — no content

---

## Cashflow Records

### List Records
```
GET /cashflow/records?year=2026&month=3
```
🔒 Requires auth. `year` is required; `month` is optional (omit to get the full year).

**Response `200`**
```json
[
  {
    "id": 1,
    "cashflow_type_id": 1,
    "cashflow_subtype_id": 2,
    "amount": "5000.00",
    "note": "March bill",
    "recorded_at": "2026-03-01"
  }
]
```

---

### Create Record
```
POST /cashflow/records
```
🔒 Requires auth.

**Body**
| Field | Type | Rules |
|---|---|---|
| `recorded_at` | date | required |
| `cashflow_type_id` | integer | required, must be the user's own type |
| `cashflow_subtype_id` | integer | required if the type has subtypes, otherwise omit |
| `amount` | number | required, > 0 |
| `note` | string | optional |

**Response `201`** — created record object

**Response `422`** — validation error (e.g. subtype required but missing, subtype belongs to a different type)

---

### Update Record
```
PATCH /cashflow/records/{id}
```
🔒 Requires auth. Owned records only.

**Body** (all optional)
| Field | Type |
|---|---|
| `cashflow_type_id` | integer |
| `cashflow_subtype_id` | integer |
| `amount` | number |
| `note` | string |

**Response `200`** — updated record object

**Response `403`** — not the record owner

---

### Delete Record
```
DELETE /cashflow/records/{id}
```
🔒 Requires auth. Owned records only. Soft-deletes.

**Response `204`** — no content

---

### Bulk Operations
```
POST /cashflow/records/bulk
```
🔒 Requires auth. Executes creates, updates, and deletes in one request.

**Body**
| Field | Type | Rules |
|---|---|---|
| `year` | integer | required |
| `month` | integer | required |
| `creates` | array | optional — each item: `{ cashflow_type_id, cashflow_subtype_id, amount, note }` |
| `updates` | array | optional — each item: `{ id, amount, note }` |
| `deletes` | array | optional — array of record IDs to soft-delete |

Updates and deletes for records not owned by the user are silently skipped.

**Response `200`**
```json
{
  "created": [ { "id": 5, ... } ],
  "updated": 1,
  "deleted": 2
}
```

---

## Cashflow Import / Export

### Export Cashflow Records
```
GET /cashflow/export?format=csv|json
```
🔒 Requires auth. Returns the user's cashflow records. If no records exist, a single example row is included.

**Response `200`** — streamed CSV (`text/csv`) or JSON array (`application/json`)

CSV columns: `year, month, type, subtype, amount, note`

---

### Preview Cashflow Import
```
POST /cashflow/import/preview
```
🔒 Requires auth. Validates rows and checks for duplicates without writing to the database.

**Body (multipart/form-data)**
| Field | Type | Rules |
|---|---|---|
| `file` | file | required, CSV or JSON |
| `format` | string | required, `csv` or `json` |

**Response `200`**
```json
{
  "total": 2,
  "valid": 1,
  "invalid": [ { "row": 2, "reason": "Unknown type" } ],
  "duplicates": []
}
```

---

### Import Cashflow Records
```
POST /cashflow/import
```
🔒 Requires auth. Types and subtypes must already exist for the user — unknown types are skipped, not created.

**Body (multipart/form-data)**
| Field | Type | Rules |
|---|---|---|
| `file` | file | required, CSV or JSON |
| `format` | string | required, `csv` or `json` |
| `skip_duplicates` | boolean | optional, default `true` |

**Response `200`**
```json
{
  "imported": 1,
  "skipped": [ { "row": 2, "reason": "Unknown type: Rent" } ]
}
```

---

## Error Responses

| Status | Meaning |
|---|---|
| `401` | Unauthenticated — missing or invalid token |
| `403` | Forbidden — authenticated but not authorized |
| `404` | Resource not found |
| `422` | Validation failed — body contains `errors` map |
| `500` | Server error |

**Validation error shape**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Error message."]
  }
}
```

---

## Notes

- All timestamps are in UTC. The app uses **Asia/Taipei (UTC+8)** for trading day calculations.
- Stock prices are fetched from **Yahoo Finance** (`query1.finance.yahoo.com/v8/finance/chart`). TWSE symbols use `.TW`; OTC symbols use `.TWO`. The service auto-retries `.TW` symbols with `.TWO` on 404.
- Market index symbols (e.g. `^TWII`, `^IXIC`, `^VIX`) are supported but cannot be transacted — they are tracked for price history and charting only.
- Handling fees follow Taiwan brokerage standard: **0.1425%** of trade value, minimum NT$20, reduced by `handling_fee_discount`.
- Transaction tax applies to **sell** orders only: **0.3%** of trade value.
- All models use **soft deletes** — deleted records are excluded from normal queries and can be restored.
