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
```json
{
  "message": "The given data was invalid.",
  "errors": { "email": ["The provided credentials are incorrect."] }
}
```

---

### Get Current User
```
GET /auth/me
```
🔒 Requires auth

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
    "last_fetched_at": "2026-03-09T08:00:00.000000Z"
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
| `symbol` | string | required, max 15, alphanumeric + optional dot suffix (e.g. `2330.TW`) |
| `name` | string | required, max 255 |

Symbol is automatically uppercased.

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
🔒 Requires auth. Fetches the latest price from Yahoo Finance synchronously.

- For `.TW` symbols not found on Yahoo Finance, automatically retries with `.TWO` (Taiwan OTC market).

**Response `200`** — updated stock object

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

## Price History

### Get Price History for a Stock
```
GET /stocks/{symbol}/prices
```
Public. Returns daily closing prices up to and including yesterday (Taiwan time). Today is excluded to prevent incomplete intraday data.

**Response `200`**
```json
[
  { "id": 1, "stock_id": 1, "date": "2026-01-30", "close_price": "72.6000" },
  { "id": 2, "stock_id": 1, "date": "2026-02-02", "close_price": "71.5000" }
]
```

---

## Transactions

### Create Transaction
```
POST /transactions
```
🔒 Requires auth.

**Body**
| Field | Type | Rules |
|---|---|---|
| `stock_id` | integer | required, must exist |
| `type` | string | required, `buy` or `sell` |
| `shares` | number | required, > 0 |
| `price_per_share` | number | required, > 0 |
| `transacted_at` | date | required |
| `notes` | string | optional |

Handling fee and transaction tax are auto-calculated from the user's `handling_fee_discount` setting:
- **Handling fee** = `max(20, floor(tradeValue × 0.1425% × (1 − discount)))`
- **Transaction tax** = `floor(tradeValue × 0.3%)` for sells; `0` for buys

For sell transactions, validates that shares owned ≥ shares to sell.

If `transacted_at` is not today, dispatches a background job to fetch historical price data for that date.

**Response `201`** — created transaction object with `stock` relationship

**Response `422`** — validation error or insufficient shares

---

### Update Transaction
```
PUT /transactions/{id}
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

Fee and tax are accepted as-is (user-overridable). If `transacted_at` is not today, dispatches a background job to fetch historical price data for the new date.

**Response `200`** — updated transaction object with `stock` relationship

**Response `403`** — not the transaction owner

---

### Delete Transaction
```
DELETE /transactions/{id}
```
🔒 Requires auth. Only the transaction owner may delete. Soft-deletes the record.

**Response `204`** — no content

**Response `403`** — not the transaction owner

---

## Portfolio

### Get Portfolio Positions
```
GET /portfolio
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
      "last_fetched_at": "2026-03-09T08:00:00.000000Z"
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
| `unrealized_gain` | `current_value − (average_cost × net_shares) − estimated sell fee − estimated sell tax` |
| `realized_gain` | Net proceeds from sells minus the average cost of shares sold |

---

### Get Portfolio Value History
```
GET /portfolio/history
```
🔒 Requires auth. Returns daily portfolio value and cost basis from the earliest transaction date up to yesterday (Taiwan time).

For each date, only shares held at that date are included (i.e. future purchases are not counted back in time). Dates where total value is 0 or any close price is 0 are omitted.

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
GET /settings
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
PATCH /settings
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

- All timestamps are in UTC. The app uses **Asia/Taipei (UTC+8)** for trading day calculations (price history, daily close cut-off).
- Stock prices are fetched from **Yahoo Finance** (`query1.finance.yahoo.com/v8/finance/chart`). Taiwan TWSE symbols use `.TW` suffix; OTC symbols use `.TWO`. The API automatically falls back from `.TW` to `.TWO` when a symbol is not found.
- Handling fees follow Taiwan brokerage standard: **0.1425%** of trade value, minimum NT$20, reduced by the user's `handling_fee_discount`.
- Transaction tax applies to **sell** orders only: **0.3%** of trade value.
- All models use **soft deletes** — deleted records remain in the database with a `deleted_at` timestamp and are excluded from normal queries.
