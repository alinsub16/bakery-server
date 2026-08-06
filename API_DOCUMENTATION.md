# Bakery Inventory Management System — API Documentation

Base URL: `http://localhost:8000/api/v1` (adjust for your environment)

All authenticated endpoints require a Sanctum bearer token:
```
Authorization: Bearer {token}
```

All responses are JSON. Validation errors follow Laravel's standard shape:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Error message."]
  }
}
```

---

## Table of Contents

1. [Authentication](#1-authentication)
2. [Roles & User Management](#2-roles--user-management)
3. [Categories](#3-categories)
4. [Breads](#4-breads)
5. [Opening Balance](#5-opening-balance)
6. [Daily Production](#6-daily-production)
7. [Inventory (Closing Stock)](#7-inventory-closing-stock)
8. [Sales Reports](#8-sales-reports)
9. [Dashboard](#9-dashboard)
10. [Activity Logs](#10-activity-logs)
11. [Production Variance Report](#11-production-variance-report)
12. [Error Reference](#12-error-reference)
13. [Rate Limits](#13-rate-limits)

---

## 1. Authentication

### Register (public self-registration)
```
POST /register
```
No auth required. Creates a `pending` account — **no token issued**. An admin must approve before login works.

**Request**
```json
{
  "name": "New Baker",
  "email": "newbaker@bakery.test",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Response `201`**
```json
{ "message": "Registration submitted. An admin will review your account." }
```

**Response `422`** — duplicate email, password mismatch, etc.

---

### Login
```
POST /login
```
No auth required.

**Request**
```json
{ "email": "admin@bakery.test", "password": "password123" }
```

**Response `200`**
```json
{
  "token": "1|abcdef123456...",
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@bakery.test",
    "role": "admin"
  }
}
```

**Response `422`** — one of several messages depending on account state:
| Condition | `errors.email[0]` |
|---|---|
| Wrong password / unknown email | `"Invalid credentials."` |
| Deactivated account | `"This account has been deactivated."` |
| Pending self-registration | `"Your account is pending admin approval."` |
| Rejected self-registration | `"This account registration was not approved."` |

Token expires after **7 days**.

---

### Logout
```
POST /logout
```
Auth required. Revokes only the current token.

**Response `200`**
```json
{ "message": "Logged out" }
```

---

### Get current user
```
GET /me
```
Auth required.

**Response `200`**
```json
{
  "id": 1,
  "name": "Admin User",
  "email": "admin@bakery.test",
  "role": "admin"
}
```

---

## 2. Roles & User Management

### List roles
```
GET /roles
```
**Auth:** admin

**Response `200`**
```json
[
  { "id": 1, "name": "admin" },
  { "id": 2, "name": "manager" },
  { "id": 3, "name": "baker" },
  { "id": 4, "name": "inventory_clerk" }
]
```

---

### List active users
```
GET /users
```
**Auth:** admin. Paginated. Only `status = active` users appear here.

**Response `200`**
```json
{
  "current_page": 1,
  "data": [
    { "id": 2, "name": "Baker One", "email": "baker@bakery.test", "role": "baker" }
  ],
  "last_page": 1,
  "per_page": 20,
  "total": 1
}
```

---

### List pending registrations
```
GET /users/pending
```
**Auth:** admin. The approval queue.

**Response `200`**
```json
{
  "data": [
    { "id": 5, "name": "New Baker", "email": "newbaker@bakery.test", "registered_at": "2026-08-04T09:00:00Z" }
  ]
}
```

---

### Admin-create a user (active immediately)
```
POST /users
```
**Auth:** admin

**Request**
```json
{
  "name": "Direct Hire",
  "email": "directhire@bakery.test",
  "password": "password123",
  "role": "inventory_clerk"
}
```

**Response `201`**
```json
{
  "id": 6,
  "name": "Direct Hire",
  "email": "directhire@bakery.test",
  "role": "inventory_clerk"
}
```

---

### Change a user's role
```
PUT /users/{user}/role
```
**Auth:** admin

**Request**
```json
{ "role": "manager" }
```

**Response `200`**
```json
{ "id": 2, "name": "Baker One", "email": "baker@bakery.test", "role": "manager" }
```

**Response `403`**
- `"You cannot change your own role."`
- `"Cannot remove the last remaining admin."`

**Response `422`** — invalid role name.

---

### Approve a pending user
```
PATCH /users/{user}/approve
```
**Auth:** admin. Sets `status = active` and assigns the given role in one step.

**Request**
```json
{ "role": "baker" }
```

**Response `200`**
```json
{ "id": 5, "name": "New Baker", "email": "newbaker@bakery.test", "role": "baker", "status": "active" }
```

**Response `409`** — user is not currently pending.

---

### Reject a pending user
```
PATCH /users/{user}/reject
```
**Auth:** admin. No body required.

**Response `200`**
```json
{ "id": 5, "status": "rejected" }
```

**Response `409`** — user is not currently pending.

---

### Deactivate a user
```
PATCH /users/{user}/deactivate
```
**Auth:** admin. Soft-deletes; blocks login. No body required.

**Response `200`**
```json
{ "message": "User deactivated." }
```

**Response `403`**
- `"You cannot deactivate your own account."`
- `"Cannot deactivate the last remaining admin."`

---

### Reactivate a user
```
PATCH /users/{id}/activate
```
**Auth:** admin. Note: uses `{id}`, not `{user}` — resolves soft-deleted records explicitly. No body required.

**Response `200`**
```json
{ "message": "User activated." }
```

---

## 3. Categories

### List categories
```
GET /categories
```
**Auth:** any authenticated user. Paginated.

**Query params:** `is_active` (bool), `search` (string, matches name)

**Response `200`**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Wheat Loaves",
      "slug": "wheat-loaves",
      "description": "Standard and whole wheat loaves",
      "is_active": true,
      "created_at": "2026-07-01T00:00:00Z",
      "updated_at": "2026-07-01T00:00:00Z"
    }
  ]
}
```

### Get a category
```
GET /categories/{category}
```
**Auth:** any authenticated user. Same shape as above, single object under `data`.

### Create a category
```
POST /categories
```
**Auth:** admin, manager

**Request**
```json
{ "name": "Pastries", "description": "Flaky baked goods" }
```
**Response `201`** — created category. `slug` auto-generated. **Response `422`** on duplicate name.

### Update a category
```
PUT /categories/{category}
```
**Auth:** admin, manager. Same body shape as create. Slug regenerates from the new name.

### Deactivate / Activate
```
PATCH /categories/{category}/deactivate
PATCH /categories/{category}/activate
```
**Auth:** admin, manager. No body. Toggles `is_active`. Category is never hard-deleted.

---

## 4. Breads

### List breads
```
GET /breads
```
**Auth:** any authenticated user. Paginated.

**Query params:** `category_id`, `is_active`, `search` (matches name or sku)

**Response `200`**
```json
{
  "data": [
    {
      "id": 1,
      "name": "White Sandwich Loaf",
      "sku": "BRD-001",
      "unit": "pcs",
      "selling_price": 45.0,
      "cost_price": 20.0,
      "is_active": true,
      "category": { "id": 1, "name": "Wheat Loaves" },
      "created_at": "2026-07-01T00:00:00Z",
      "updated_at": "2026-07-01T00:00:00Z"
    }
  ]
}
```

### Get a bread
```
GET /breads/{bread}
```
**Auth:** any authenticated user.

### Create a bread
```
POST /breads
```
**Auth:** admin, manager

**Request**
```json
{
  "category_id": 1,
  "name": "White Sandwich Loaf",
  "sku": "BRD-001",
  "unit": "pcs",
  "selling_price": 45.00,
  "cost_price": 20.00
}
```
`unit` optional (defaults `pcs`). `cost_price` optional. **Response `422`** on duplicate SKU or nonexistent category.

### Update a bread
```
PUT /breads/{bread}
```
**Auth:** admin, manager. Same body shape as create (full replacement, not partial).

### Deactivate / Activate
```
PATCH /breads/{bread}/deactivate
PATCH /breads/{bread}/activate
```
**Auth:** admin, manager. No body.

---

## 5. Opening Balance

One-time starting stock entry for a bread that existed before the system was adopted. Can only be set **once**, and only **before** any production or inventory activity exists for that bread.

### Get a bread's opening balance
```
GET /breads/{bread}/opening-balance
```
**Auth:** any authenticated user.

**Response `200`**
```json
{
  "bread_id": 3,
  "quantity": 15,
  "note": "Physical count during system migration, Aug 4 2026",
  "set_by": { "id": 1, "name": "Admin User" },
  "created_at": "2026-08-04T09:00:00Z"
}
```

**Response `404`** — none has been set for this bread.

### Set a bread's opening balance
```
POST /breads/{bread}/opening-balance
```
**Auth:** admin, manager

**Request**
```json
{
  "quantity": 15,
  "note": "Physical count during system migration, Aug 4 2026"
}
```
`note` required, min 5 characters — always leave a reason.

**Response `201`** — same shape as GET above.

**Response `409`**
- `"This bread already has activity history; opening balance can only be set before any production or inventory has been recorded."`
- `"An opening balance has already been recorded for this bread."`

---

## 6. Daily Production

### List production entries
```
GET /production
```
**Auth:** any authenticated user. Paginated.

**Query params:** `date` (defaults today), `bread_id`

**Response `200`**
```json
{
  "data": [
    {
      "id": 1,
      "bread": { "id": 1, "name": "White Sandwich Loaf", "sku": "BRD-001" },
      "production_date": "2026-08-04",
      "quantity_produced": 50,
      "produced_by": { "id": 3, "name": "Baker One" },
      "created_at": "2026-08-04T06:00:00Z"
    }
  ]
}
```

### Submit today's production
```
POST /production
```
**Auth:** admin, manager, baker, inventory_clerk

**Request**
```json
{ "bread_id": 1, "quantity_produced": 50 }
```
`production_date` is always today — never accepted from the client.

**Response `201`** — created record.

**Response `409`** — production already recorded for this bread today.

**Response `422`** — inactive/nonexistent bread, or `quantity_produced < 1`.

### Correct today's production
```
PUT /production/{production}
```
**Auth:** admin, manager only. Same-day only. Blocked once closing inventory exists for that bread+day. Logged to Activity Logs.

**Request**
```json
{ "quantity_produced": 45 }
```

**Response `200`** — updated record.

**Response `403`** — `"Cannot correct production from a previous day."`

**Response `409`** — `"Cannot correct — closing inventory has already been recorded for this bread today."`

---

## 7. Inventory (Closing Stock)

### List inventory entries
```
GET /inventory
```
**Auth:** any authenticated user. Paginated.

**Query params:** `date` (defaults today), `bread_id`

**Response `200`**
```json
{
  "data": [
    {
      "id": 1,
      "bread": { "id": 1, "name": "White Sandwich Loaf", "sku": "BRD-001" },
      "inventory_date": "2026-08-04",
      "opening_stock": 50,
      "closing_stock": 10,
      "sold_quantity": 40,
      "revenue": 1800.0,
      "recorded_by": { "id": 4, "name": "Clerk One" },
      "created_at": "2026-08-04T18:00:00Z"
    }
  ]
}
```

### Check today's opening stock (before closing)
```
GET /inventory/opening-stock/{bread}
```
**Auth:** any authenticated user.

**Response `200`**
```json
{ "bread_id": 1, "opening_stock": 50, "production_date": "2026-08-04" }
```

### Submit today's closing stock
```
POST /inventory
```
**Auth:** admin, manager, inventory_clerk

**Request**
```json
{ "bread_id": 1, "closing_stock": 10 }
```
`opening_stock`, `sold_quantity`, `revenue` are always server-calculated — never accepted from the client.

**Response `201`** — created record with all calculated fields.

**Response `409`** — closing inventory already recorded for this bread today.

**Response `422`** — `closing_stock > opening_stock`.

### Correct today's closing stock
```
PUT /inventory/{inventory}
```
**Auth:** admin, manager only. Same-day only. Recalculates `sold_quantity`/`revenue`. Logged to Activity Logs.

**Request**
```json
{ "closing_stock": 8 }
```

**Response `200`** — updated record.

**Response `403`** — `"Cannot correct closing inventory from a previous day."`

**Response `422`** — new value exceeds opening stock.

---

## 8. Sales Reports

All read-only, any authenticated user.

### Daily summary
```
GET /sales/daily-summary?date=2026-08-04
```
`date` optional, defaults today.

**Response `200`**
```json
{
  "total_sold_quantity": 40,
  "total_revenue": 1800.0,
  "total_cost": 800.0,
  "total_profit": 1000.0,
  "breads_reported": 1,
  "date": "2026-08-04"
}
```

### Date range
```
GET /sales/range?from=2026-08-01&to=2026-08-31
```
`from`/`to` required. Max 90-day span → `422` if exceeded. Optional `category_id`.

**Response `200`**
```json
{
  "from": "2026-08-01",
  "to": "2026-08-31",
  "total_sold_quantity": 1200,
  "total_revenue": 54000.0,
  "total_cost": 24000.0,
  "total_profit": 30000.0,
  "breads_reported": 5,
  "daily_breakdown": [
    { "date": "2026-08-01", "sold_quantity": 40, "revenue": 1800.0, "profit": 1000.0 }
  ]
}
```

### By-bread breakdown
```
GET /sales/by-bread?from=2026-08-01&to=2026-08-31
```
Same params as range. Sorted by `total_revenue` descending.

**Response `200`**
```json
[
  {
    "bread": { "id": 3, "name": "Chocolate Croissant", "sku": "BRD-003" },
    "total_sold_quantity": 300,
    "total_revenue": 13500.0,
    "total_profit": 7500.0
  }
]
```

### Monthly
```
GET /sales/monthly?year=2026&month=8
```
Both required. `month` 1–12.

**Response `200`** — same totals shape + `daily_breakdown` (only days with data).

### Yearly
```
GET /sales/yearly?year=2026
```
**Response `200`** — same totals shape + `monthly_breakdown` (**all 12 months present**, zero-filled where no activity).

---

## 9. Dashboard

```
GET /dashboard/summary?low_stock_threshold=10
```
**Auth:** any authenticated user. `low_stock_threshold` optional, defaults `10`.

**Response `200`**
```json
{
  "date": "2026-08-04",
  "today": {
    "total_sold_quantity": 42,
    "total_revenue": 1890.0,
    "total_profit": 1050.0,
    "breads_reported": 1
  },
  "pending": {
    "needs_production": [
      { "id": 3, "name": "Chocolate Croissant", "sku": "BRD-003" }
    ],
    "needs_closing": [
      { "id": 1, "name": "White Sandwich Loaf", "sku": "BRD-001", "quantity_produced": 50 }
    ]
  },
  "low_stock": [
    { "id": 2, "name": "Cinnamon Bun", "sku": "BRD-002", "opening_stock": 5 }
  ],
  "week_trend": {
    "this_week_revenue": 12500.0,
    "last_week_revenue": 11000.0,
    "change_percent": 13.64
  }
}
```
`needs_production` — active breads with no production entry today.
`needs_closing` — breads produced today but not yet closed.
`change_percent` is `null` when there's no prior-week revenue to compare against.

---

## 10. Activity Logs

```
GET /activity-logs
```
**Auth:** admin, manager only. Paginated. Read-only audit trail — populated automatically by Production/Inventory corrections.

**Query params:** `subject_type`, `action`, `user_id`, `from`, `to`

**Response `200`**
```json
{
  "data": [
    {
      "id": 1,
      "action": "production.corrected",
      "subject_type": "DailyProduction",
      "subject_id": 1,
      "properties": {
        "bread_id": 1,
        "production_date": "2026-08-04",
        "old_quantity": 500,
        "new_quantity": 50
      },
      "user": { "id": 1, "name": "Admin User" },
      "created_at": "2026-08-04T10:00:00Z"
    }
  ]
}
```

---

## 11. Production Variance Report

```
GET /reports/production-variance?from=2026-08-01&to=2026-08-31
```
**Auth:** any authenticated user. Max 90-day span. Optional `category_id`. Identifies consistently overproduced breads.

**Response `200`**
```json
{
  "from": "2026-08-01",
  "to": "2026-08-31",
  "breads": [
    {
      "bread": { "id": 3, "name": "Chocolate Croissant", "sku": "BRD-003" },
      "total_produced": 900,
      "total_sold": 580,
      "variance": 320,
      "variance_percent": 35.56,
      "avg_daily_closing_stock": 10.3,
      "days_with_production": 30,
      "days_with_pending_closing": 2
    }
  ]
}
```
Sorted by `variance_percent` descending — worst overproducers first. Only breads with production activity in the range appear.

---

## 12. Error Reference

| Status | Meaning | Common causes |
|---|---|---|
| `401` | Unauthenticated | Missing/invalid/expired token |
| `403` | Forbidden | Role doesn't permit this action; self-protection guard triggered; correction attempted after the day passed or after downstream data exists |
| `404` | Not found | Resource ID doesn't exist |
| `409` | Conflict | Duplicate submission for the day; opening balance already set; approving/rejecting a non-pending user |
| `422` | Validation failed | Bad input shape, business rule violation (e.g. closing > opening stock) |
| `429` | Too many requests | Rate limit exceeded — see below |

---

## 13. Rate Limits

| Limiter | Scope | Limit |
|---|---|---|
| `login` | `/login`, `/register` | 5 attempts/min, keyed by IP + email |
| `api` | All authenticated routes | 120 requests/min per user (or IP if unauthenticated) |
| `api-writes` | All POST/PUT/PATCH mutation routes | 30 requests/min per user (stacked on top of `api`) |

`429` responses include a `Retry-After` header (seconds) and `X-RateLimit-Remaining: 0`.
