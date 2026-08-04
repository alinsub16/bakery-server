# Bakery Inventory Management System — Backend API

A production-ready backend for managing a bakery's daily production, closing inventory, sales, and reporting — built with Laravel, PHP 8.3+, and PostgreSQL.

## Business Flow

```
Opening Stock  = Opening Balance (one-time, first day only) + Yesterday's Closing Stock + Today's Production
Sold Quantity  = Opening Stock − Closing Stock
Revenue        = Sold Quantity × Bread Selling Price (locked in at closing time)
```

- `sold_quantity` and `revenue` are always system-calculated and never accepted as direct input.
- Closing stock automatically becomes the next day's opening remaining stock.
- Production and closing entries are limited to **today only** — no backdating.
- Inventory history is never deleted. Corrections are handled through controlled, audit-logged update endpoints.
- A bread that existed before this system was adopted can have a one-time **Opening Balance** recorded, so pre-existing physical stock is accounted for correctly on its first tracked day.

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel |
| Language | PHP 8.3+ |
| Database | PostgreSQL |
| Auth | Laravel Sanctum (token-based) |
| Testing | Pest |
| Frontend (planned) | React + TypeScript + Tailwind |

## Getting Started

```bash
# Install dependencies
composer install

# Environment setup
cp .env.example .env
php artisan key:generate

# Configure your PostgreSQL connection in .env
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=bakery_api
# DB_USERNAME=your_username
# DB_PASSWORD=your_password

# Set your frontend origin for CORS
# FRONTEND_URL=http://localhost:5173

# Run migrations
php artisan migrate

# Seed baseline roles (admin, manager, baker, inventory_clerk)
php artisan db:seed --class=RoleSeeder

# Publish CORS config if not already present
php artisan config:publish cors

# Serve the app
php artisan serve
```

### Creating your first admin user

Accounts can now be created two ways: an admin creating one directly (active immediately), or a public self-registration that waits for admin approval. For your very first admin account, use tinker:

```bash
php artisan tinker
```
```php
$user = App\Models\User::create([
    'name' => 'Admin User',
    'email' => 'admin@bakery.test',
    'password' => Hash::make('password123'),
    'status' => 'active',
]);
$user->roles()->attach(App\Models\Role::where('name', 'admin')->first());
```

## Roles

| Role | Description |
|---|---|
| `admin` | Full access — user/role management, account approval, all mutations, audit log visibility |
| `manager` | Manages categories/breads/opening balances, corrects production & inventory entries, views audit log |
| `baker` | Submits daily production entries |
| `inventory_clerk` | Submits daily production and closing stock entries |

Every user holds exactly one role at a time.

## Account Lifecycle

New accounts can be created two ways:

1. **Admin-created** (`POST /users`) — active immediately, role assigned at creation.
2. **Self-registered** (`POST /register`) — public endpoint, no role selected by the registrant. Account starts in `pending` status and cannot log in until an admin reviews it.

An account's `status` is one of:
- `pending` — awaiting admin review, cannot log in
- `active` — normal, functioning account
- `rejected` — reviewed and denied, cannot log in

Admins can also `deactivate`/`activate` (soft delete/restore) an already-active account. Self-protection guards prevent an admin from deactivating or role-changing themselves, and prevent removing the last remaining admin.

## Modules Implemented

1. **Authentication** — Sanctum login/logout, token expiration (7 days), public self-registration
2. **Roles & Permissions** — role assignment, protected against self-demotion and removing the last admin
3. **User Management** — admin-created accounts, pending-account approval/rejection queue, deactivate/activate
4. **Categories** — CRUD with soft delete, activate/deactivate (no hard delete)
5. **Bread Management** — CRUD linked to categories, per-bread pricing (`selling_price`, `cost_price`)
6. **Opening Balance** — one-time starting stock entry for breads that existed before system adoption
7. **Daily Production** — one entry per bread per day, admin/manager correction with audit trail
8. **Inventory (Closing Stock)** — auto-calculated opening stock, sold quantity, and revenue; admin/manager correction with audit trail
9. **Sales Reports** — daily summary, date range, by-bread breakdown, monthly, yearly
10. **Dashboard** — consolidated summary: today's totals, pending production/closing, low-stock alerts, week-over-week trend
11. **Activity Logs** — admin/manager-only viewer for all correction and audit events
12. **Reports** — production vs. sales variance report (identifies consistently overproduced breads)

## Security

- Named rate limiters: `api` (120 req/min per user/IP), `api-writes` (30 req/min, stacked on all mutation routes), `login` (5 attempts/min, keyed by IP + email, also applied to `/register`)
- Sanctum tokens expire after 7 days
- CORS restricted to a single configured frontend origin (`FRONTEND_URL`)
- Security headers middleware (`X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`) applied to all API responses
- Generic, non-enumerating error messages on login; informative (non-generic) messages for pending/rejected accounts, since those aren't security-sensitive
- **Before deploying:** ensure `APP_ENV=production` and `APP_DEBUG=false` — debug mode exposes full stack traces and file paths in error responses

## API Endpoints

Base URL: `/api/v1`

### Auth
| Method | Endpoint | Auth |
|---|---|---|
| POST | `/register` | Guest |
| POST | `/login` | Guest |
| POST | `/logout` | Sanctum |
| GET | `/me` | Sanctum |

### Roles & Users
| Method | Endpoint | Auth |
|---|---|---|
| GET | `/roles` | admin |
| GET | `/users` | admin |
| GET | `/users/pending` | admin |
| POST | `/users` | admin |
| PUT | `/users/{user}/role` | admin |
| PATCH | `/users/{user}/approve` | admin |
| PATCH | `/users/{user}/reject` | admin |
| PATCH | `/users/{user}/deactivate` | admin |
| PATCH | `/users/{id}/activate` | admin |

### Categories
| Method | Endpoint | Auth |
|---|---|---|
| GET | `/categories` | any |
| GET | `/categories/{category}` | any |
| POST | `/categories` | admin, manager |
| PUT | `/categories/{category}` | admin, manager |
| PATCH | `/categories/{category}/activate` | admin, manager |
| PATCH | `/categories/{category}/deactivate` | admin, manager |

### Breads
| Method | Endpoint | Auth |
|---|---|---|
| GET | `/breads` | any |
| GET | `/breads/{bread}` | any |
| POST | `/breads` | admin, manager |
| PUT | `/breads/{bread}` | admin, manager |
| PATCH | `/breads/{bread}/activate` | admin, manager |
| PATCH | `/breads/{bread}/deactivate` | admin, manager |
| GET | `/breads/{bread}/opening-balance` | any |
| POST | `/breads/{bread}/opening-balance` | admin, manager (one-time only) |

### Production
| Method | Endpoint | Auth |
|---|---|---|
| GET | `/production` | any |
| POST | `/production` | admin, manager, baker, inventory_clerk |
| PUT | `/production/{production}` | admin, manager (same-day only, logged) |

### Inventory
| Method | Endpoint | Auth |
|---|---|---|
| GET | `/inventory` | any |
| GET | `/inventory/opening-stock/{bread}` | any |
| POST | `/inventory` | admin, manager, inventory_clerk |
| PUT | `/inventory/{inventory}` | admin, manager (same-day only, logged) |

### Sales Reports
| Method | Endpoint |
|---|---|
| GET | `/sales/daily-summary` |
| GET | `/sales/range` |
| GET | `/sales/by-bread` |
| GET | `/sales/monthly` |
| GET | `/sales/yearly` |

### Dashboard
| Method | Endpoint |
|---|---|
| GET | `/dashboard/summary` |

### Activity Logs *(admin, manager only)*
| Method | Endpoint |
|---|---|
| GET | `/activity-logs` |

### Reports
| Method | Endpoint |
|---|---|
| GET | `/reports/production-variance` |

## Testing

Automated test coverage via Pest, spanning all 12 modules:

- `tests/Unit/Services/InventoryCalculationServiceTest.php` — opening stock, sold quantity, revenue, and opening balance calculation logic in isolation
- `tests/Feature/AuthTest.php`, `RegistrationTest.php` — login, logout, token expiry, registration, pending/rejected account handling
- `tests/Feature/RoleManagementTest.php`, `UserManagementTest.php` — role changes, approval/rejection, deactivate/activate, self-protection guards
- `tests/Feature/CategoryTest.php`, `BreadTest.php`, `OpeningBalanceTest.php` — CRUD, uniqueness rules, role restrictions
- `tests/Feature/DailyProductionTest.php`, `DailyInventoryTest.php` — same-day constraints, correction rules, auto-calculation
- `tests/Feature/SalesReportTest.php`, `DashboardTest.php`, `ActivityLogTest.php`, `ProductionVarianceTest.php` — aggregation correctness, access control

### Running tests

Tests run against a dedicated PostgreSQL test database (not SQLite), since several migrations use Postgres-specific `CHECK` constraints that must be exercised faithfully.

```bash
# One-time setup
php artisan key:generate --env=testing
# Ensure .env.testing points DB_CONNECTION=pgsql at a real test database,
# and that phpunit.xml does not override DB_CONNECTION to sqlite.

# Run the full suite
./vendor/bin/pest

# Run a specific file
./vendor/bin/pest tests/Feature/DailyInventoryTest.php
```

Key rules covered across the suite:
- Duplicate production/inventory submissions for the same bread + day → `409`
- Closing stock exceeding opening stock → `422`
- Corrections blocked after the entry's day has passed → `403`
- Role-restricted endpoints reject unauthorized roles → `403`
- Opening balance can only be set once, and only before any production/inventory history exists → `409`
- Pending/rejected accounts cannot log in; self-registration issues no token
- Rate limits return `429` with `Retry-After` once exceeded

## Roadmap

- [ ] Frontend (React + TypeScript + Tailwind)
- [ ] Password reset flow
- [ ] Denied-action logging (403s, failed logins) in activity log
- [ ] Account lockout after repeated failed logins
