# Bakery Inventory Management System — Backend API

A production-ready backend for managing a bakery's daily production, closing inventory, sales, and reporting — built with Laravel 12, PHP 8.3+, and PostgreSQL.

## Business Flow

```
Opening Stock  = Yesterday's Closing Stock + Today's Production
Sold Quantity  = Opening Stock − Closing Stock
Revenue        = Sold Quantity × Bread Selling Price (locked in at closing time)
```

- `sold_quantity` and `revenue` are always system-calculated and never accepted as direct input.
- Closing stock automatically becomes the next day's opening remaining stock.
- Production and closing entries are limited to **today only** — no backdating.
- Inventory history is never deleted. Corrections are handled through controlled, audit-logged update endpoints.

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12 |
| Language | PHP 8.3+ |
| Database | PostgreSQL |
| Auth | Laravel Sanctum (token-based) |
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

### Creating your first user (no self-registration endpoint yet)

```bash
php artisan tinker
```
```php
$user = App\Models\User::create([
    'name' => 'Admin User',
    'email' => 'admin@bakery.test',
    'password' => 'password123',
]);
$user->roles()->attach(App\Models\Role::where('name', 'admin')->first());
```

## Roles

| Role | Description |
|---|---|
| `admin` | Full access — user/role management, all mutations, audit log visibility |
| `manager` | Manages categories/breads, corrects production & inventory entries, views audit log |
| `baker` | Submits daily production entries |
| `inventory_clerk` | Submits daily production and closing stock entries |

Every user holds exactly one role at a time.

## Modules Implemented

1. **Authentication** — Sanctum login/logout, token expiration (7 days)
2. **Roles & Permissions** — role assignment, protected against self-demotion and removing the last admin
3. **Categories** — CRUD with soft delete, activate/deactivate (no hard delete)
4. **Bread Management** — CRUD linked to categories, per-bread pricing (`selling_price`, `cost_price`)
5. **Daily Production** — one entry per bread per day, admin/manager correction with audit trail
6. **Inventory (Closing Stock)** — auto-calculated opening stock, sold quantity, and revenue; admin/manager correction with audit trail
7. **Sales Reports** — daily summary, date range, by-bread breakdown, monthly, yearly
8. **Dashboard** — consolidated summary: today's totals, pending production/closing, low-stock alerts, week-over-week trend
9. **Activity Logs** — admin/manager-only viewer for all correction and audit events
10. **Reports** — production vs. sales variance report (identifies consistently overproduced breads)

## Security

- Named rate limiters: `api` (120 req/min per user/IP), `api-writes` (30 req/min, stacked on all mutation routes), `login` (5 attempts/min, keyed by IP + email)
- Sanctum tokens expire after a month
- CORS restricted to a single configured frontend origin (`FRONTEND_URL`)
- Security headers middleware (`X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`) applied to all API responses
- Generic, non-enumerating error messages on login
- **Before deploying:** ensure `APP_ENV=production` and `APP_DEBUG=false` — debug mode exposes full stack traces and file paths in error responses

## API Endpoints

Base URL: `/api/v1`

### Auth
| Method | Endpoint | Auth |
|---|---|---|
| POST | `/login` | Guest |
| POST | `/logout` | Sanctum |
| GET | `/me` | Sanctum |

### Roles & Users *(admin only)*
| Method | Endpoint |
|---|---|
| GET | `/roles` |
| GET | `/users` |
| PUT | `/users/{user}/role` |

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

All endpoints have been manually verified via Postman/Bruno throughout development. Key rules covered:

- Duplicate production/inventory submissions for the same bread + day → `409`
- Closing stock exceeding opening stock → `422`
- Corrections blocked after the entry's day has passed → `403`
- Role-restricted endpoints reject unauthorized roles → `403`
- Rate limits return `429` with `Retry-After` once exceeded

Automated test coverage (PHPUnit/Pest) is not yet implemented — recommended as a next step before frontend integration.

## Roadmap

- [ ] Frontend (React + TypeScript + Tailwind)
- [ ] User self-management / password reset flow
- [ ] Opening balance / initial stock backfill for system launch
- [ ] Denied-action logging (403s, failed logins) in activity log
- [ ] Account lockout after repeated failed logins
- [ ] Automated test suite
