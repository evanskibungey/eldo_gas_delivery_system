# EldoGas — Comprehensive Development Plan
### Laravel · Inertia.js · React · MySQL

> **Document type:** Engineering Development Plan  
> **System scope:** Customer Web Frontend + Admin Backend Portal  
> **Future scope:** REST API layer for Flutter customer & rider mobile apps  
> **Classification:** Confidential

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Technology Stack](#2-technology-stack)
3. [Architecture Overview](#3-architecture-overview)
4. [Database Schema Design](#4-database-schema-design)
5. [Phase 1 — Foundation & Infrastructure](#phase-1--foundation--infrastructure)
6. [Phase 2 — Admin: Authentication & Core Setup](#phase-2--admin-authentication--core-setup)
7. [Phase 3 — Admin: Product Catalogue Management](#phase-3--admin-product-catalogue-management)
8. [Phase 4 — Admin: Stock Management](#phase-4--admin-stock-management)
9. [Phase 5 — Admin: Rider Management](#phase-5--admin-rider-management)
10. [Phase 6 — Customer: Authentication & Onboarding](#phase-6--customer-authentication--onboarding)
11. [Phase 7 — Customer: Ordering System](#phase-7--customer-ordering-system)
12. [Phase 8 — Admin: Order Management & Dispatch](#phase-8--admin-order-management--dispatch)
13. [Phase 9 — Exception & Edge-Case Flows](#phase-9--exception--edge-case-flows)
14. [Phase 10 — Notification System](#phase-10--notification-system)
15. [Phase 11 — GasPoints Loyalty Programme](#phase-11--gaspoints-loyalty-programme)
16. [Phase 12 — Safety Features](#phase-12--safety-features)
17. [Phase 13 — Admin: Analytics & Reporting](#phase-13--admin-analytics--reporting)
18. [Phase 14 — REST API Layer (Flutter-Ready)](#phase-14--rest-api-layer-flutter-ready)
19. [Phase 15 — Testing, Security & Performance](#phase-15--testing-security--performance)
20. [Phase 16 — Deployment & DevOps](#phase-16--deployment--devops)
21. [Appendix A — Directory Structure](#appendix-a--directory-structure)
22. [Appendix B — Naming Conventions](#appendix-b--naming-conventions)
23. [Appendix C — Environment Variables](#appendix-c--environment-variables)

---

## 1. Project Overview

EldoGas is a mobile-first, on-demand LPG (cooking gas) delivery platform serving households, restaurants, and businesses across Kenyan urban and peri-urban areas. The platform operates a single-shop model where a shop dispatches verified riders to deliver pre-filled cylinders directly to customers' doors.

### System Components

| Component | Technology | Status |
|---|---|---|
| Customer Web Frontend | Laravel + Inertia.js + React | **In scope — this plan** |
| Admin Backend Portal | Laravel + Inertia.js + React | **In scope — this plan** |
| REST API | Laravel API (Sanctum) | **In scope — Phase 14** |
| Customer Mobile App | Flutter | Future — not in this plan |
| Rider Mobile App | Flutter | Future — not in this plan |

### Core Business Rules
- Single shop, multiple riders
- Two order types: **Swap** (refill) and **New Cylinder**
- Five cylinder sizes: 3kg, 6kg, 13kg, 25kg, 50kg
- Payment: Cash or M-Pesa Till (STK Push in Phase 2)
- All prices, sizes, brands, and add-ons are **Admin-managed** — nothing is hard-coded
- Stock is deducted automatically when a rider taps "Picked Up" (not at order placement)

---

## 2. Technology Stack

### Backend
| Package | Version | Purpose |
|---|---|---|
| PHP | ≥ 8.2 | Runtime |
| Laravel | 11.x | Application framework |
| Inertia.js (server) | `inertiajs/inertia-laravel` | SPA bridge |
| Laravel Sanctum | Bundled | API authentication (tokens) |
| Laravel Reverb | 1.x | WebSockets for live order tracking |
| Spatie Laravel Permission | 6.x | Role & permission management |
| Spatie Laravel Activity Log | 4.x | Audit logging (stock, price changes) |
| Laravel Horizon | 5.x | Queue monitoring |
| Laravel Telescope | 5.x | Local debug tooling |
| Laravel Excel | 3.x | CSV/XLSX report exports |
| Twilio SDK / AfricasTalking | Latest | SMS delivery |
| `propaganistas/laravel-phone` | 5.x | Phone number validation |

### Frontend
| Package | Version | Purpose |
|---|---|---|
| Node.js | ≥ 20 LTS | Build tooling |
| Vite | 5.x | Asset bundling |
| React | 18.x | UI library |
| Inertia.js (client) | `@inertiajs/react` | SPA routing |
| TypeScript | 5.x | Type safety |
| Tailwind CSS | 3.x | Utility-first styling |
| shadcn/ui | Latest | Component library |
| Lucide React | Latest | Icon set |
| React Hook Form | 7.x | Form management |
| Zod | 3.x | Schema validation |
| TanStack Query | 5.x | Server state management |
| Leaflet.js / React-Leaflet | 4.x | Map & live tracking |
| Recharts | 2.x | Admin analytics charts |
| `@radix-ui/*` | Latest | Accessible UI primitives |
| date-fns | 3.x | Date formatting |
| Axios | 1.x | HTTP client |

### Infrastructure
| Tool | Purpose |
|---|---|
| MySQL 8.x | Primary database |
| Redis | Queues, cache, WebSocket adapter |
| Laravel Horizon | Queue monitoring |
| Supervisor | Queue worker management |
| Nginx | Web server |
| Laravel Forge / Ploi | Server provisioning (recommended) |
| GitHub Actions | CI/CD |

---

## 3. Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                        Laravel Application                           │
│                                                                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────────┐  │
│  │  Web Routes  │  │  API Routes  │  │     Inertia Middleware    │  │
│  │  (Inertia)   │  │  (Sanctum)   │  │     (SSR-ready)           │  │
│  └──────┬───────┘  └──────┬───────┘  └──────────────────────────┘  │
│         │                 │                                          │
│  ┌──────▼─────────────────▼───────────────────────────────────┐    │
│  │                    Controller Layer                          │    │
│  │  Admin/* | Customer/* | Auth/* | Api/V1/*                   │    │
│  └──────────────────────────┬───────────────────────────────────┘   │
│                             │                                        │
│  ┌──────────────────────────▼───────────────────────────────────┐   │
│  │                    Service Layer                              │   │
│  │  OrderService | StockService | NotificationService           │   │
│  │  GasPointsService | RiderDispatchService | PaymentService    │   │
│  └──────────────────────────┬───────────────────────────────────┘   │
│                             │                                        │
│  ┌──────────────────────────▼───────────────────────────────────┐   │
│  │              Repository / Eloquent Models Layer               │   │
│  └──────────────────────────┬───────────────────────────────────┘   │
│                             │                                        │
│  ┌──────────────────────────▼───────────────────────────────────┐   │
│  │                    MySQL Database                             │   │
│  └───────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐              │
│  │  Job Queue   │  │  WebSockets  │  │  Cache/Redis │              │
│  │  (Redis)     │  │  (Reverb)    │  │              │              │
│  └──────────────┘  └──────────────┘  └──────────────┘              │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────────────────┐   ┌──────────────────────────┐
│  Customer Web Frontend   │   │  Admin Backend Portal    │
│  React + Inertia         │   │  React + Inertia         │
│  (subdomain: app.)       │   │  (subdomain: admin.)     │
└──────────────────────────┘   └──────────────────────────┘

┌──────────────────────────────────────────────────────────┐
│  Future: Flutter Apps (Customer + Rider)                  │
│  Consume: /api/v1/* (Laravel Sanctum token auth)         │
└──────────────────────────────────────────────────────────┘
```

### Key Architectural Decisions

1. **Domain-driven directory structure** — feature folders, not type folders
2. **Service layer** — business logic lives in Services, not Controllers
3. **Action classes** — single-responsibility operations (e.g., `PlaceSwapOrderAction`)
4. **Form Requests** — all validation in dedicated Request classes
5. **API Resources** — all JSON responses through Resource classes (future-proofs the Flutter API)
6. **Events & Listeners** — order state changes fire Events, Listeners handle side effects (notifications, stock, GasPoints)
7. **Jobs** — SMS and push notifications dispatched as queued jobs
8. **Policies** — all authorization via Laravel Policies

---

## 4. Database Schema Design

### 4.1 Users & Authentication

```sql
-- Admin users
CREATE TABLE admins (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) UNIQUE NOT NULL,
  password      VARCHAR(255) NOT NULL,
  is_active     BOOLEAN DEFAULT TRUE,
  last_login_at TIMESTAMP NULL,
  created_at    TIMESTAMP,
  updated_at    TIMESTAMP
);

-- Customers
CREATE TABLE customers (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name             VARCHAR(100) NOT NULL,
  phone            VARCHAR(20) UNIQUE NOT NULL,
  phone_verified_at TIMESTAMP NULL,
  gaspoints_balance INT UNSIGNED DEFAULT 0,
  referral_code    VARCHAR(12) UNIQUE NOT NULL,
  referred_by      BIGINT UNSIGNED NULL REFERENCES customers(id),
  is_active        BOOLEAN DEFAULT TRUE,
  created_at       TIMESTAMP,
  updated_at       TIMESTAMP
);

-- OTP tokens for customer login
CREATE TABLE otp_tokens (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  phone       VARCHAR(20) NOT NULL,
  token       VARCHAR(4) NOT NULL,  -- 4-digit OTP
  expires_at  TIMESTAMP NOT NULL,
  used_at     TIMESTAMP NULL,
  created_at  TIMESTAMP,
  INDEX idx_phone_token (phone, token)
);

-- Customer saved addresses
CREATE TABLE customer_addresses (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id  BIGINT UNSIGNED NOT NULL REFERENCES customers(id),
  label        ENUM('Home','Office','Restaurant','Other') DEFAULT 'Home',
  latitude     DECIMAL(10,8) NOT NULL,
  longitude    DECIMAL(11,8) NOT NULL,
  description  VARCHAR(255) NULL,
  is_default   BOOLEAN DEFAULT FALSE,
  created_at   TIMESTAMP,
  updated_at   TIMESTAMP,
  INDEX idx_customer (customer_id)
);
```

### 4.2 Product Catalogue

```sql
-- Cylinder sizes (admin-managed)
CREATE TABLE cylinder_sizes (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(20) NOT NULL,     -- e.g. '13kg'
  weight_kg     DECIMAL(5,1) NOT NULL,
  sort_order    INT UNSIGNED DEFAULT 0,
  is_commercial BOOLEAN DEFAULT FALSE,   -- 25kg, 50kg = TRUE
  is_active     BOOLEAN DEFAULT TRUE,
  created_at    TIMESTAMP,
  updated_at    TIMESTAMP
);

-- Gas brands (admin-managed)
CREATE TABLE gas_brands (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  logo_path  VARCHAR(255) NULL,
  is_active  BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);

-- Brand availability per size
CREATE TABLE brand_size_availability (
  brand_id  BIGINT UNSIGNED REFERENCES gas_brands(id),
  size_id   BIGINT UNSIGNED REFERENCES cylinder_sizes(id),
  PRIMARY KEY (brand_id, size_id)
);

-- Pricing (admin-managed, logged on change)
CREATE TABLE cylinder_prices (
  id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  size_id              BIGINT UNSIGNED NOT NULL REFERENCES cylinder_sizes(id),
  gas_refill_price     INT UNSIGNED NOT NULL,   -- swap price in KES
  new_cylinder_price   INT UNSIGNED NOT NULL,   -- hardware price in KES
  new_gas_fill_price   INT UNSIGNED NOT NULL,   -- gas fill for new cylinder in KES
  delivery_fee         INT UNSIGNED NOT NULL,   -- delivery fee in KES
  updated_by           BIGINT UNSIGNED REFERENCES admins(id),
  created_at           TIMESTAMP,
  updated_at           TIMESTAMP,
  UNIQUE KEY uq_size (size_id)
);

-- Add-on groups (e.g. "Regulator Options", "Gas Hose Options")
CREATE TABLE addon_groups (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  size_id       BIGINT UNSIGNED NOT NULL REFERENCES cylinder_sizes(id),
  name          VARCHAR(100) NOT NULL,
  selection_type ENUM('multi','single') NOT NULL DEFAULT 'multi',
  sort_order    INT UNSIGNED DEFAULT 0,
  is_active     BOOLEAN DEFAULT TRUE,
  created_at    TIMESTAMP,
  updated_at    TIMESTAMP
);

-- Individual add-on items
CREATE TABLE addon_items (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id    BIGINT UNSIGNED NOT NULL REFERENCES addon_groups(id),
  name        VARCHAR(150) NOT NULL,
  description VARCHAR(255) NULL,
  price       INT UNSIGNED NOT NULL DEFAULT 0,
  photo_path  VARCHAR(255) NULL,
  sort_order  INT UNSIGNED DEFAULT 0,
  is_active   BOOLEAN DEFAULT TRUE,
  created_at  TIMESTAMP,
  updated_at  TIMESTAMP
);
```

### 4.3 Stock Management

```sql
-- Stock levels (one row per size)
CREATE TABLE stock_levels (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  size_id               BIGINT UNSIGNED UNIQUE NOT NULL REFERENCES cylinder_sizes(id),
  filled_count          INT UNSIGNED NOT NULL DEFAULT 0,
  empty_count           INT UNSIGNED NOT NULL DEFAULT 0,
  low_stock_threshold   INT UNSIGNED NOT NULL DEFAULT 5,
  updated_by            BIGINT UNSIGNED NULL REFERENCES admins(id),
  updated_at            TIMESTAMP,
  created_at            TIMESTAMP
);

-- Full audit log of every stock change
CREATE TABLE stock_audit_logs (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  size_id       BIGINT UNSIGNED NOT NULL REFERENCES cylinder_sizes(id),
  change_type   ENUM('auto_deduction','auto_return','manual_adjustment','out_of_stock_cancel') NOT NULL,
  change_amount INT NOT NULL,              -- positive = added, negative = removed
  new_count     INT UNSIGNED NOT NULL,
  order_id      BIGINT UNSIGNED NULL,     -- linked if auto change
  admin_id      BIGINT UNSIGNED NULL REFERENCES admins(id),
  note          TEXT NULL,
  created_at    TIMESTAMP,
  INDEX idx_size (size_id),
  INDEX idx_order (order_id)
);
```

### 4.4 Riders

```sql
CREATE TABLE riders (
  id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name                 VARCHAR(100) NOT NULL,
  phone                VARCHAR(20) UNIQUE NOT NULL,
  photo_path           VARCHAR(255) NULL,
  national_id          VARCHAR(20) NULL,
  is_safety_certified  BOOLEAN DEFAULT FALSE,
  certification_date   DATE NULL,
  is_active            BOOLEAN DEFAULT TRUE,
  is_available         BOOLEAN DEFAULT FALSE,  -- online/offline toggle
  current_latitude     DECIMAL(10,8) NULL,
  current_longitude    DECIMAL(11,8) NULL,
  location_updated_at  TIMESTAMP NULL,
  avg_rating           DECIMAL(3,2) DEFAULT 0.00,
  total_deliveries     INT UNSIGNED DEFAULT 0,
  created_at           TIMESTAMP,
  updated_at           TIMESTAMP
);
```

### 4.5 Orders

```sql
CREATE TABLE orders (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number     VARCHAR(20) UNIQUE NOT NULL,  -- e.g. #1042
  customer_id      BIGINT UNSIGNED NOT NULL REFERENCES customers(id),
  rider_id         BIGINT UNSIGNED NULL REFERENCES riders(id),
  size_id          BIGINT UNSIGNED NOT NULL REFERENCES cylinder_sizes(id),
  brand_id         BIGINT UNSIGNED NULL REFERENCES gas_brands(id),
  order_type       ENUM('swap','new_cylinder') NOT NULL,
  status           ENUM(
                     'pending',        -- placed, no rider yet
                     'rider_assigned', -- rider accepted
                     'picked_up',      -- rider collected from shop
                     'on_the_way',     -- en route to customer
                     'delivered',      -- completed successfully
                     'cancelled'       -- cancelled for any reason
                   ) NOT NULL DEFAULT 'pending',

  -- Pricing snapshot (immutable at order time)
  gas_price        INT UNSIGNED NOT NULL,
  cylinder_price   INT UNSIGNED NOT NULL DEFAULT 0,
  delivery_fee     INT UNSIGNED NOT NULL,
  addons_total     INT UNSIGNED NOT NULL DEFAULT 0,
  total_amount     INT UNSIGNED NOT NULL,

  payment_method   ENUM('cash','mpesa') NOT NULL,
  payment_status   ENUM('pending','collected','disputed','refunded') DEFAULT 'pending',

  -- Delivery location
  delivery_lat     DECIMAL(10,8) NOT NULL,
  delivery_lng     DECIMAL(11,8) NOT NULL,
  delivery_notes   TEXT NULL,

  -- Timestamps for each stage
  rider_assigned_at TIMESTAMP NULL,
  picked_up_at      TIMESTAMP NULL,
  delivered_at      TIMESTAMP NULL,
  cancelled_at      TIMESTAMP NULL,
  cancel_reason     VARCHAR(255) NULL,
  cancelled_by      ENUM('customer','admin','system') NULL,

  -- Exception tracking
  has_issue        BOOLEAN DEFAULT FALSE,
  issue_type       VARCHAR(100) NULL,
  issue_resolved   BOOLEAN DEFAULT FALSE,

  created_at       TIMESTAMP,
  updated_at       TIMESTAMP,

  INDEX idx_customer (customer_id),
  INDEX idx_rider (rider_id),
  INDEX idx_status (status),
  INDEX idx_created (created_at)
);

-- Add-ons selected for a specific order
CREATE TABLE order_addons (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id     BIGINT UNSIGNED NOT NULL REFERENCES orders(id),
  addon_item_id BIGINT UNSIGNED NOT NULL REFERENCES addon_items(id),
  price        INT UNSIGNED NOT NULL,  -- snapshot at time of order
  INDEX idx_order (order_id)
);

-- Order status history (immutable log)
CREATE TABLE order_status_history (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id   BIGINT UNSIGNED NOT NULL REFERENCES orders(id),
  status     VARCHAR(50) NOT NULL,
  note       VARCHAR(255) NULL,
  actor_type VARCHAR(50) NULL,   -- 'customer','rider','admin','system'
  actor_id   BIGINT UNSIGNED NULL,
  created_at TIMESTAMP,
  INDEX idx_order (order_id)
);

-- Delivery ratings
CREATE TABLE order_ratings (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    BIGINT UNSIGNED UNIQUE NOT NULL REFERENCES orders(id),
  customer_id BIGINT UNSIGNED NOT NULL REFERENCES customers(id),
  rider_id    BIGINT UNSIGNED NOT NULL REFERENCES riders(id),
  stars       TINYINT UNSIGNED NOT NULL,
  tags        JSON NULL,           -- ['Fast','Friendly','Safe handling','Professional']
  review      TEXT NULL,
  flagged     BOOLEAN DEFAULT FALSE,
  flag_reason VARCHAR(255) NULL,
  created_at  TIMESTAMP
);
```

### 4.6 GasPoints

```sql
CREATE TABLE gaspoints_transactions (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id   BIGINT UNSIGNED NOT NULL REFERENCES customers(id),
  order_id      BIGINT UNSIGNED NULL REFERENCES orders(id),
  type          ENUM('earned','redeemed','bonus','referral','referral_bonus') NOT NULL,
  points        INT NOT NULL,          -- positive = earned, negative = redeemed
  balance_after INT UNSIGNED NOT NULL,
  description   VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP,
  INDEX idx_customer (customer_id)
);
```

### 4.7 Notifications

```sql
CREATE TABLE notifications_log (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipient_type ENUM('customer','rider','admin') NOT NULL,
  recipient_id   BIGINT UNSIGNED NOT NULL,
  channel        ENUM('push','sms','in_app') NOT NULL,
  trigger        VARCHAR(100) NOT NULL,
  message        TEXT NOT NULL,
  sent_at        TIMESTAMP NULL,
  failed_at      TIMESTAMP NULL,
  error          TEXT NULL,
  created_at     TIMESTAMP,
  INDEX idx_recipient (recipient_type, recipient_id)
);
```

---

## Phase 1 — Foundation & Infrastructure

**Goal:** A running Laravel + Inertia.js + React project with environment, routing, layouts, and CI in place.  
**Validate before proceeding:** All tasks below pass, `php artisan test` is green, and `npm run build` succeeds.

---

### 1.1 Project Scaffolding

- [ ] Create new Laravel 11 project: `composer create-project laravel/laravel eldogas`
- [ ] Install Inertia.js server-side adapter: `composer require inertiajs/inertia-laravel`
- [ ] Publish Inertia middleware: `php artisan inertia:middleware`
- [ ] Register `HandleInertiaRequests` middleware in `bootstrap/app.php`
- [ ] Install React + Inertia client: `npm install @inertiajs/react react react-dom`
- [ ] Configure Vite with `@vitejs/plugin-react` and `laravel-vite-plugin`
- [ ] Install and configure TypeScript: `npm install -D typescript @types/react @types/react-dom`
- [ ] Create `tsconfig.json` with strict mode and path aliases (`@/` → `resources/js/`)
- [ ] Install Tailwind CSS: `npm install -D tailwindcss postcss autoprefixer` then `npx tailwindcss init -p`
- [ ] Configure Tailwind `content` paths to cover `resources/js/**`
- [ ] Install shadcn/ui: `npx shadcn@latest init`
- [ ] Install base shadcn components: `button`, `input`, `card`, `badge`, `dialog`, `dropdown-menu`, `table`, `select`, `toast`
- [ ] Install Lucide React: `npm install lucide-react`
- [ ] Install supporting packages: `react-hook-form`, `zod`, `@hookform/resolvers`, `date-fns`, `axios`, `clsx`

### 1.2 Environment Setup

- [ ] Configure `.env` for local development (MySQL, Redis, mail, queue driver = Redis)
- [ ] Create `.env.example` with all required keys (no values)
- [ ] Configure `config/database.php` for MySQL with strict mode enabled
- [ ] Set `APP_URL`, `ASSET_URL`, and `SESSION_DOMAIN` for subdomain routing
- [ ] Configure Redis in `config/cache.php` and `config/queue.php`
- [ ] Set queue connection to `redis` in `.env`
- [ ] Configure `config/reverb.php` for WebSocket server
- [ ] Add all third-party service keys to `.env` (Africa's Talking SMS, M-Pesa)

### 1.3 Database & Migrations

- [ ] Create all migrations in order (respect foreign key dependencies):
  - `admins`
  - `customers`, `otp_tokens`, `customer_addresses`
  - `cylinder_sizes`, `gas_brands`, `brand_size_availability`
  - `cylinder_prices`
  - `addon_groups`, `addon_items`
  - `stock_levels`, `stock_audit_logs`
  - `riders`
  - `orders`, `order_addons`, `order_status_history`, `order_ratings`
  - `gaspoints_transactions`
  - `notifications_log`
- [ ] Run `php artisan migrate` — verify all tables created correctly
- [ ] Create `DatabaseSeeder` with `--seed` flag for development data
- [ ] Create seeders: `AdminSeeder`, `CylinderSizeSeeder`, `GasBrandSeeder`, `CylinderPriceSeeder`, `StockLevelSeeder`
- [ ] Seed all five cylinder sizes (3kg, 6kg, 13kg, 25kg, 50kg) with placeholder prices
- [ ] Seed default gas brands (Total, Rubis, Lake Gas, Hashi)
- [ ] Seed default add-on groups and items for all sizes
- [ ] Run `php artisan db:seed` — verify data integrity

### 1.4 Eloquent Models

Create all Eloquent models with:
- [ ] `Admin` — fillable, hidden (password), casts, `HasRoles` (Spatie)
- [ ] `Customer` — fillable, hidden, casts, `HasApiTokens` (Sanctum), `referralCode` accessor
- [ ] `CustomerAddress` — fillable, `belongsTo(Customer)`
- [ ] `OtpToken` — fillable, `isExpired()` method, `isUsed()` method
- [ ] `CylinderSize` — fillable, `hasMany(AddonGroup)`, `hasOne(CylinderPrice)`, `hasOne(StockLevel)`, `scopeActive()`
- [ ] `GasBrand` — fillable, `belongsToMany(CylinderSize, 'brand_size_availability')`, `scopeActive()`
- [ ] `CylinderPrice` — fillable, `belongsTo(CylinderSize)`, `belongsTo(Admin, 'updated_by')`
- [ ] `AddonGroup` — fillable, `hasMany(AddonItem)`, `belongsTo(CylinderSize)`, `scopeActive()`
- [ ] `AddonItem` — fillable, `belongsTo(AddonGroup)`, `scopeActive()`, `getPhotoUrlAttribute()`
- [ ] `StockLevel` — fillable, `belongsTo(CylinderSize)`, `isLow()` method, `isCritical()` method, `isEmpty()` method
- [ ] `StockAuditLog` — fillable, `belongsTo(CylinderSize)`, `belongsTo(Order)`, `belongsTo(Admin)`
- [ ] `Rider` — fillable, `hasMany(Order)`, `hasMany(OrderRating)`, `getAvatarUrlAttribute()`
- [ ] `Order` — fillable, casts (JSON for future use), all relationships, `getFormattedTotalAttribute()`, `scopeActive()`, `scopeByStatus()`
- [ ] `OrderAddon` — fillable, `belongsTo(Order)`, `belongsTo(AddonItem)`
- [ ] `OrderStatusHistory` — fillable, `belongsTo(Order)`, `morphTo(actor)`
- [ ] `OrderRating` — fillable, casts (tags as array), all relationships
- [ ] `GasPointsTransaction` — fillable, `belongsTo(Customer)`, `belongsTo(Order)`
- [ ] `NotificationLog` — fillable

### 1.5 Routing Structure

```php
// routes/web.php

// Admin routes (prefix: /admin)
Route::prefix('admin')->name('admin.')->group(function () {
    require base_path('routes/admin.php');
});

// Customer routes (prefix: none or /app)
Route::name('customer.')->group(function () {
    require base_path('routes/customer.php');
});
```

- [ ] Create `routes/admin.php` — all admin web routes
- [ ] Create `routes/customer.php` — all customer web routes
- [ ] Create `routes/api.php` — API routes (Phase 14)
- [ ] Create `routes/channels.php` — WebSocket broadcasting channels

### 1.6 Layouts & Shell Components

**Admin layout:**
- [ ] `resources/js/Layouts/AdminLayout.tsx` — sidebar, header, breadcrumbs, flash messages
- [ ] `resources/js/Components/Admin/Sidebar.tsx` — navigation links with icons
- [ ] `resources/js/Components/Admin/TopBar.tsx` — page title, user menu, notifications bell

**Customer layout:**
- [ ] `resources/js/Layouts/CustomerLayout.tsx` — header (logo, GasPoints balance, profile), page body
- [ ] `resources/js/Layouts/GuestLayout.tsx` — centered card for login/onboarding screens
- [ ] `resources/js/Components/Customer/BottomNav.tsx` — mobile-first bottom navigation

**Shared components:**
- [ ] `resources/js/Components/Shared/FlashMessage.tsx` — success/error toast
- [ ] `resources/js/Components/Shared/LoadingSpinner.tsx`
- [ ] `resources/js/Components/Shared/ConfirmDialog.tsx` — destructive action confirmation
- [ ] `resources/js/Components/Shared/EmptyState.tsx` — zero-data placeholder
- [ ] `resources/js/Components/Shared/StatusBadge.tsx` — order status colour-coded badges

### 1.7 CI/CD & Git Setup

- [ ] Initialise Git repository with `.gitignore` (include `.env`, `vendor/`, `node_modules/`, `storage/`)
- [ ] Create `main`, `develop`, and `feature/*` branch strategy
- [ ] Create `.github/workflows/ci.yml`:
  - PHP lint (`pint`), tests (`php artisan test`), static analysis (`phpstan`)
  - Node lint (`eslint`), type check (`tsc --noEmit`), build (`npm run build`)
- [ ] Add `composer.json` scripts: `test`, `lint`, `analyse`
- [ ] Set up Husky pre-commit hooks for lint and type-check

**✅ Phase 1 Checkpoint:** `php artisan serve` runs, home page renders via Inertia, all migrations pass, CI pipeline is green.

---

## Phase 2 — Admin: Authentication & Core Setup

**Goal:** Secure admin login, role management, and the admin shell.

### 2.1 Admin Authentication

- [ ] Install Spatie Laravel Permission: `composer require spatie/laravel-permission`
- [ ] Publish and run Spatie migrations: `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`
- [ ] Define roles in seeder: `super_admin`, `shop_manager`, `dispatcher`
- [ ] Create `AdminAuthController` with `showLogin`, `login`, `logout` methods
- [ ] Build `resources/js/Pages/Admin/Auth/Login.tsx` — email/password form, EldoGas branding
- [ ] Create `app/Http/Requests/Admin/LoginRequest.php` — validate email, password, rate-limit (5 attempts per minute)
- [ ] Implement `remember_me` functionality
- [ ] Create `RedirectIfAdminAuthenticated` middleware
- [ ] Create `EnsureAdminIsAuthenticated` middleware
- [ ] Protect all admin routes with `EnsureAdminIsAuthenticated`
- [ ] Log successful and failed logins to `activity_log` (Spatie)
- [ ] Build `resources/js/Pages/Admin/Auth/ForgotPassword.tsx` and `ResetPassword.tsx`

### 2.2 Admin User Management

- [ ] Create `AdminUserController` (CRUD)
- [ ] Build `resources/js/Pages/Admin/Users/Index.tsx` — data table with search, role filter
- [ ] Build `resources/js/Pages/Admin/Users/Create.tsx` and `Edit.tsx`
- [ ] Form validation: name, email, password strength, role assignment
- [ ] Implement `is_active` toggle — deactivated admins cannot log in
- [ ] Seed first super admin via `AdminSeeder` or Artisan command: `php artisan admin:create`

**✅ Phase 2 Checkpoint:** Admin can log in, see the dashboard shell, manage other admin users, and log out. Unauthorized access redirects to login.

---

## Phase 3 — Admin: Product Catalogue Management

**Goal:** Admin can fully manage cylinder sizes, gas brands, prices, and add-ons. Nothing is hard-coded.

### 3.1 Cylinder Sizes

- [ ] Create `CylinderSizeController` (CRUD + toggle active)
- [ ] Build `resources/js/Pages/Admin/Catalogue/Sizes/Index.tsx` — sortable list, active toggle
- [ ] Build `Create.tsx` / `Edit.tsx` — name, weight, is_commercial flag, sort order
- [ ] Add policy: only `super_admin` and `shop_manager` can manage sizes
- [ ] When a size is deactivated, orders for that size cannot be placed (enforced at order creation)
- [ ] Write Feature tests: create, update, deactivate, verify deactivation blocks ordering

### 3.2 Gas Brands

- [ ] Create `GasBrandController` (CRUD + logo upload + toggle active)
- [ ] Build `resources/js/Pages/Admin/Catalogue/Brands/Index.tsx`
- [ ] Build `Create.tsx` / `Edit.tsx` — name, logo upload (stored in `storage/app/public/brands/`), size availability checkboxes
- [ ] Validate logo: max 1MB, jpg/png/webp
- [ ] When a brand is deactivated, it no longer appears in the ordering flow
- [ ] Write Feature tests

### 3.3 Pricing Management

- [ ] Create `CylinderPriceController` (`index`, `update` — one price record per size)
- [ ] Build `resources/js/Pages/Admin/Catalogue/Pricing/Index.tsx` — table showing all sizes with current prices, inline edit buttons
- [ ] Build `Edit.tsx` — form for gas_refill_price, new_cylinder_price, new_gas_fill_price, delivery_fee
- [ ] On save: log the price change to `activity_log` with old and new values, timestamp, and admin user
- [ ] Add `PriceChangedEvent` — fires after every update
- [ ] Write Feature tests: price update logged, values reflected immediately in ordering flow

### 3.4 Add-On Group & Item Management

- [ ] Create `AddonGroupController` (CRUD, scoped to a cylinder size)
- [ ] Create `AddonItemController` (CRUD, scoped to an addon group)
- [ ] Build `resources/js/Pages/Admin/Catalogue/Addons/Index.tsx` — accordion by cylinder size, drag-to-reorder (sort_order), active toggle
- [ ] Build group form: name, selection_type (multi/single), sort_order
- [ ] Build item form: name, description, price, photo upload, sort_order
- [ ] Photo upload: stored in `storage/app/public/addons/`, max 2MB
- [ ] Implement drag-to-reorder using sort_order field
- [ ] Write Feature tests

**✅ Phase 3 Checkpoint:** Admin can manage the full product catalogue. Prices update immediately. Add-on structure matches the specification for all 5 cylinder sizes.

---

## Phase 4 — Admin: Stock Management

**Goal:** Admin can set, view, and adjust stock; low-stock alerts fire automatically.

### 4.1 Stock Level Display

- [ ] Create `StockController` with `index` and `update` methods
- [ ] Build `resources/js/Pages/Admin/Stock/Index.tsx`:
  - Table: cylinder size | filled count | empty count | low-stock threshold | status (OK / LOW / CRITICAL / OUT)
  - Colour-coded status badges (green / amber / red / dark red)
  - "Adjust Stock" button per row
  - "Audit Log" link per row

### 4.2 Stock Adjustment

- [ ] Build `resources/js/Pages/Admin/Stock/Adjust.tsx` — form: new filled count, new empty count, optional note
- [ ] `StockService::manualAdjust(size, filledCount, emptyCount, adminId, note)`:
  - Updates `stock_levels`
  - Creates `StockAuditLog` entry with `change_type = manual_adjustment`
  - If new count > 0 and size was auto-hidden (empty), fires `StockRestoredEvent`
  - If new count = 0, fires `StockDepletedEvent`
- [ ] Write Feature tests

### 4.3 Stock Audit Log

- [ ] Build `resources/js/Pages/Admin/Stock/AuditLog.tsx` — filterable by size, date range, change type
- [ ] Include linked order numbers as clickable links

### 4.4 Low-Stock Alerts

- [ ] Create `CheckLowStockJob` — runs every 15 minutes via scheduler
- [ ] `StockAlertService::check()` — queries all stock_levels, compares to threshold
- [ ] Fire `LowStockAlertEvent` when count ≤ threshold
- [ ] Fire `CriticalStockAlertEvent` when count ≤ 2
- [ ] Fire `StockDepletedEvent` when count = 0 (triggers auto-hide in customer app)
- [ ] Listen to `StockDepletedEvent` → mark `cylinder_sizes.is_active = false` temporarily, log action
- [ ] Listen to `StockRestoredEvent` → re-activate size, send admin notification
- [ ] Admin dashboard: stock alert badges on sidebar navigation icon
- [ ] Write tests for threshold triggers and auto-hide logic

**✅ Phase 4 Checkpoint:** Stock adjustments save correctly, audit log records every change, low-stock alerts appear on the dashboard, and depleted sizes are automatically hidden.

---

## Phase 5 — Admin: Rider Management

**Goal:** Admin can add, manage, and monitor riders.

### 5.1 Rider CRUD

- [ ] Create `RiderController` (index, create, store, edit, update, destroy/deactivate)
- [ ] Build `resources/js/Pages/Admin/Riders/Index.tsx`:
  - Table: name, phone, status (Available/Unavailable/On delivery), rating, deliveries, Safety Certified badge
  - Filters: available only, active only
- [ ] Build `Create.tsx` / `Edit.tsx`: name, phone, national ID, photo upload, safety certification (checkbox + date)
- [ ] Photo upload to `storage/app/public/riders/`
- [ ] Safety certification: `is_safety_certified`, `certification_date` — admins can toggle and backdate
- [ ] `is_active = false` prevents rider from logging in to the Rider App (future Flutter)
- [ ] Write Feature tests

### 5.2 Rider Profile & Stats

- [ ] Build `resources/js/Pages/Admin/Riders/Show.tsx`:
  - Profile card (photo, name, phone, safety badge, join date)
  - Stats: total deliveries, avg rating, total earnings (from completed orders)
  - Recent orders table
  - Ratings & reviews list
  - Incident log (wrong deliveries, missed deliveries)
- [ ] Calculate `avg_rating` and `total_deliveries` via Eloquent accessors or computed columns

### 5.3 Rider Status Board (Live)

- [ ] Build `resources/js/Components/Admin/RiderStatusBoard.tsx`:
  - Live list of all active riders
  - Status indicator: Available (green) / On Delivery (amber) / Unavailable (grey)
  - Current order number if on delivery
- [ ] Use polling (30-second interval) in Phase 5; upgrade to WebSocket in Phase 10

**✅ Phase 5 Checkpoint:** Admin can fully manage riders. Rider status board shows live availability. Safety certification is tracked.

---

## Phase 6 — Customer: Authentication & Onboarding

**Goal:** Customer can register, verify their phone via OTP, set their name, and land on the home screen. Delivery location is **not** captured at sign-up — it is collected during the first order placement (Phase 7), reducing friction and drop-off at the auth step.

> **Design Decision:** Asking for location immediately after sign-up creates unnecessary friction for new users. Location is most relevant when they are actively placing an order, so it is deferred to that point. Customers who already have a saved address skip the prompt entirely.

### 6.1 OTP Authentication

- [ ] Create `CustomerAuthController`: `showPhoneEntry`, `sendOtp`, `showOtpVerification`, `verifyOtp`, `logout`
- [ ] `OtpService::generate(phone)`:
  - Generate 4-digit OTP
  - Store in `otp_tokens` with `expires_at = now() + 10 minutes`
  - Dispatch `SendOtpJob`
  - Log OTP to `laravel.log` in development (`Log::info("[OTP] {phone} → {token}")`)
- [ ] `SendOtpJob` — sends OTP via Africa's Talking SMS
- [ ] `OtpService::verify(phone, token)`:
  - Check token exists, not expired, not used
  - Mark `used_at = now()`
  - Create or find `Customer` by phone
  - Issue session-based auth (no Sanctum for web)
- [ ] Rate limiting: max 3 OTP requests per phone per 10 minutes
- [ ] Phone input accepts both `07XXXXXXXX` and `01XXXXXXXX` (normalized to `+254XXXXXXXXX` server-side)
- [ ] Build `resources/js/Pages/Customer/Auth/PhoneEntry.tsx` — phone input with Kenya flag prefix (+254)
- [ ] Build `resources/js/Pages/Customer/Auth/OtpVerification.tsx` — 4-digit input, countdown timer (60s), resend button
- [ ] Build `resources/js/Pages/Customer/Auth/SetName.tsx` — text input for name (only shown on first login)
- [ ] After OTP verified: route to `SetName` if name is empty, else route directly to Home
- [ ] After `SetName` submitted: route directly to Home
- [ ] Protect customer routes with `EnsureCustomerIsAuthenticated` middleware

### 6.2 Address Management (self-service, not onboarding)

- [ ] Create `CustomerAddressController` (index, create, store, update, destroy, setDefault)
- [ ] `GET /addresses/create` renders `SetLocation.tsx` — available at any time from Profile, and linked from the Review Order screen when no address exists
- [ ] Build `resources/js/Pages/Customer/Onboarding/SetLocation.tsx`:
  - Full-screen Leaflet map
  - "Use my current location" button (browser geolocation API)
  - Nominatim autocomplete search with 400ms debounce
  - Draggable pin
  - Address label selector: Home / Office / Restaurant / Other
  - Delivery notes text field
- [ ] On submit: store in `customer_addresses` with `is_default = true` (first address)
- [ ] `?redirect_to=order_review` query param: after saving, redirects back to `/order/review` instead of the address list — used when customer adds their first address mid-order
- [ ] Allow customers to manage multiple saved addresses in Profile
- [ ] Write Feature tests for OTP flow and address creation

### 6.3 Customer Home Screen

- [ ] Create `CustomerHomeController` → passes shop status, delivery estimate, last order, GasPoints balance
- [ ] Build `resources/js/Pages/Customer/Home.tsx`:
  - Shop status banner (Open/Closed with opening time)
  - Estimated delivery time ("Delivery in ~25 mins")
  - Large "Order Gas Now" CTA button
  - Last order summary card with one-tap reorder
  - GasPoints balance chip (top-right)
  - Rotating safety tip banner (bottom)
- [ ] Shop open/closed status: admin-controlled via `shop_settings` config or simple settings table
- [ ] If shop is closed: disable the Order button, show "Opens at 7:00 AM"
- [ ] Write Feature tests

**✅ Phase 6 Checkpoint:** Customer can sign in via OTP (4-digit code), set their name on first login, and view the home screen with correct shop status. No location prompt at sign-up.

---

## Phase 7 — Customer: Ordering System

**Goal:** Customer can place a Swap order or New Cylinder order end-to-end.

### 7.1 Order Initiation

- [ ] Create `OrderController` with `initiate`, `selectSize`, `selectBrand`, `selectAddons`, `reviewOrder`, `placeOrder`, `show`, `index` methods
- [ ] Build `resources/js/Pages/Customer/Order/Initiate.tsx`:
  - "Do you have an empty cylinder?" → YES (Swap) / NO (New Cylinder)
  - Animate the split cleanly

### 7.2 Swap Order Flow

- [ ] Build `resources/js/Pages/Customer/Order/SelectSize.tsx`:
  - Grid of active cylinder sizes
  - Greyed-out + "Temporarily unavailable" badge for sizes with zero stock
  - Load from `CylinderSizeResource` via Inertia shared data
- [ ] Build `resources/js/Pages/Customer/Order/SelectBrand.tsx`:
  - Brands filtered by selected size
  - Auto-select last-used brand for returning customers
  - "Change brand" toggle
- [ ] Build `resources/js/Pages/Customer/Order/ReviewOrder.tsx` (Swap):
  - Gas refill price
  - Delivery fee
  - Total on delivery
  - Delivery address selector (dropdown of saved addresses, pre-selects default)
  - **If no address saved:** show inline prompt with link to `GET /addresses/create?redirect_to=order_review` — customer adds their address and is returned to this screen automatically
  - Delivery notes field
  - Payment method selector: Cash / M-Pesa Till
  - If M-Pesa: show till number and exact amount
  - "Place Order" button

### 7.3 Add-ons (Both Order Types)

> **Design decision (2026-04-18):** Add-ons are available for **both Swap/Refill and New Cylinder** orders.
> A customer swapping their cylinder may also need a replacement regulator, hosepipe, or other accessory.
> The `addon_groups` table has no `order_type` column — groups are scoped by `size_id` only, making
> them naturally order-type agnostic. The UI shows the add-ons section for both order types.
> The add-on card is **collapsible** — customers who don't need accessories can dismiss it with one tap.

- [x] Add-ons available for both `swap` and `new_cylinder` order types
- [x] Add-on card is collapsible/expandable with chevron toggle
- [x] Selected count badge shown in collapsed header (`2 selected`)
- [x] Backend (`PlaceOrderAction`) processes addon IDs regardless of order type
- [x] `PlaceOrderRequest` validates addon IDs against `size_id` (not `order_type`)

### 7.4 Order Placement (Backend)

- [ ] Create `PlaceOrderAction` (single-responsibility class):
  1. Validate stock availability for the requested size (throw `OutOfStockException` if zero)
  2. Lock the size's stock row (`SELECT FOR UPDATE`) to prevent race conditions
  3. Create `Order` record with price snapshot
  4. Create `OrderAddon` records for selected add-ons
  5. Create first `OrderStatusHistory` entry (status: `pending`)
  6. Fire `OrderPlacedEvent`
  7. Return the new `Order`
- [ ] `OrderPlacedEvent` → Listeners:
  - `SendOrderConfirmationNotification` — push + SMS to customer
  - `AlertAdminNewOrder` — broadcast to admin Live Order Feed
  - `DispatchRiderJob` (auto-assignment if enabled, else admin manual)
- [ ] Create `app/Http/Requests/Customer/PlaceOrderRequest.php` — validate all order fields
- [ ] Handle `OutOfStockException` — return Inertia error: "This size is currently out of stock"
- [ ] Build `resources/js/Pages/Customer/Order/Confirmation.tsx`:
  - Order number
  - Estimated delivery time
  - "Track my order" CTA → redirect to live tracking

### 7.5 Live Tracking Screen

- [ ] Create `OrderTrackingController` — returns order, rider details, status
- [ ] Build `resources/js/Pages/Customer/Order/Tracking.tsx`:
  - 4-stage status progress bar (Order Received → Rider Assigned → On the Way → Delivered)
  - Leaflet map: rider moving dot + customer pin
  - Rider card: photo, name, star rating, Safety Certified badge, call button
  - ETA countdown (updates via polling / WebSocket)
  - Collapsible order summary
  - M-Pesa till number (if applicable)
- [ ] Rider location updates: poll `/api/orders/{id}/tracking` every 10 seconds initially; upgrade to WebSocket in Phase 10
- [ ] Build `resources/js/Pages/Customer/Order/History.tsx` — list of all past orders with status and reorder button

### 7.6 Post-Delivery Rating

- [ ] Create `OrderRatingController` with `store` method
- [ ] Build `resources/js/Pages/Customer/Order/Rate.tsx`:
  - Star rating (1–5)
  - Quick-tap tags: Fast / Friendly / Safe handling / Professional
  - Optional written review
  - "Flag an issue" option (wrong cylinder, safety concern, rude rider)
  - GasPoints earned display ("+100 points!")
  - Referral nudge
- [ ] `RatingSubmittedAction`:
  - Save `OrderRating`
  - Update `riders.avg_rating`
  - Fire `RatingSubmittedEvent` → `AwardGasPointsListener`
- [ ] Write Feature tests for the complete order placement flow

**✅ Phase 7 Checkpoint:** Customer can complete a full Swap order and New Cylinder order end-to-end, including add-ons, payment selection, confirmation, live tracking, and post-delivery rating.

---

## Phase 8 — Admin: Order Management & Dispatch

**Goal:** Admin can view, manage, assign, and resolve all orders in real time.

### 8.1 Live Order Feed

- [ ] Create `AdminOrderController` with `index`, `show`, `assign`, `reassign`, `cancel` methods
- [ ] Build `resources/js/Pages/Admin/Orders/Index.tsx`:
  - Real-time order list (polling + WebSocket upgrade in Phase 10)
  - Status filter tabs: All / Pending / Active / Delivered / Cancelled
  - Each order card: order number, customer name, size, type, status badge, time elapsed, payment method
  - "Assign Rider" button on pending orders
- [ ] Build `resources/js/Pages/Admin/Orders/Show.tsx`:
  - Full order details
  - Status history timeline
  - Customer info + delivery location on map
  - Rider info (if assigned)
  - Payment status
  - Action buttons: Reassign Rider, Cancel Order, Mark Issue Resolved

### 8.2 Rider Assignment

- [ ] Build `resources/js/Components/Admin/AssignRiderModal.tsx`:
  - List of available riders sorted by proximity (if location known)
  - Rider card: name, photo, rating, current distance
  - "Assign" button
- [ ] `RiderDispatchService::assign(order, rider)`:
  - Updates `orders.rider_id` and `status = rider_assigned`
  - Updates `orders.rider_assigned_at`
  - Creates `OrderStatusHistory` entry
  - Fires `RiderAssignedEvent`
  - `RiderAssignedEvent` → Listeners:
    - `SendRiderAssignedNotification` — notifies customer ("Brian is on his way")
    - `NotifyRiderOfNewOrder` — sends order details to rider (future: push to Rider App)
- [ ] Track order status changes from rider (via API — Phase 14):
  - Rider taps "Picked Up" → `status = picked_up`, stock auto-deducted
  - Rider taps "Confirm Delivery" → `status = delivered`, earnings updated, rating prompt triggered

### 8.3 Order Cancellation (Admin)

- [ ] `CancelOrderAction(order, reason, cancelledBy)`:
  - Set `status = cancelled`, `cancelled_at`, `cancel_reason`, `cancelled_by`
  - If `picked_up`: restore stock (+1 to filled count), log to `StockAuditLog`
  - Fire `OrderCancelledEvent`
  - `OrderCancelledEvent` → send customer notification with reason
- [ ] Write Feature tests

**✅ Phase 8 Checkpoint:** Admin has full visibility and control over all orders. Rider assignment works. Order cancellation correctly handles stock restoration.

---

## Phase 9 — Exception & Edge-Case Flows

**Goal:** All six exception scenarios from the spec are handled gracefully.

### 9.1 Out of Stock (Post-Order)

- [ ] Add "Report Issue" button on active orders in the Rider App API endpoint
- [ ] Create `ReportIssueController::outOfStock(order)`:
  - Set `orders.has_issue = true`, `issue_type = out_of_stock`
  - Set `status = cancelled`
  - Fire `OutOfStockExceptionEvent`
- [ ] `OutOfStockExceptionEvent` → Listeners:
  - Send customer push + SMS: "We're sorry — out of stock" with options
  - Alert admin in dashboard
  - Log to `StockAuditLog`
- [ ] Customer sees options on their tracking screen: [Order different size] / [Cancel]
- [ ] Admin dashboard: cancelled orders display "Out of Stock" reason badge

### 9.2 Wrong Cylinder

- [ ] Customer: "Report a delivery issue" → "Wrong size / Wrong brand" option on active orders
- [ ] `WrongCylinderAction(order, reportType)`:
  - Set `has_issue = true`, `issue_type = wrong_cylinder`
  - Fire `WrongCylinderReportedEvent`
  - Notify rider in-app (API — Phase 14)
  - Update order status to a sub-state: `correction_in_progress`
  - Notify customer: "Rider is collecting the correct cylinder. Updated ETA..."
  - Flag rider record with incident
- [ ] Post-delivery wrong cylinder: separate flow via "Report delivery issue" on completed order screen
- [ ] Admin alert: immediate dashboard notification
- [ ] Write Feature tests

### 9.3 Rider No-Show / Delay

- [ ] Create `CheckRiderDelaysJob` — runs every 5 minutes via scheduler
- [ ] Logic:
  - If `rider_assigned_at < now() - 15 mins` and status still `rider_assigned`: alert admin
  - If `picked_up_at` exists and `now() > picked_up_at + 2× original_eta`: alert admin + notify customer
- [ ] Customer: "My rider hasn't arrived" button visible after ETA + 20 minutes
- [ ] `RiderNoShowAction(order)`: alerts admin, allows reassignment
- [ ] `RiderDispatchService::reassign(order, newRider)`: logs reassignment, notifies customer
- [ ] Rider flagged with `missed_delivery` incident
- [ ] Write Feature tests

### 9.4 Payment Dispute

- [ ] Rider: "Payment not confirmed" button before marking delivered
- [ ] Customer: dispute resolution — show M-Pesa SMS verification instructions
- [ ] "Escalate to shop" → admin alert: `payment_dispute` on order
- [ ] Admin resolves: "Mark as Paid" or "Initiate Return"
- [ ] Log resolution in order history

### 9.5 Customer Cancellation

- [ ] Customer: "Cancel Order" button visible on tracking screen
- [ ] `CancelOrderByCustomerAction(order)`:
  - If `status = pending`: free cancellation → `cancelled`, no penalty
  - If `status = rider_assigned`: warning shown → confirm → notify rider, cancel order
  - If `status = picked_up` or later: display "Contact the shop" message, do not allow in-app cancel
- [ ] Write Feature tests

### 9.6 Damaged / Unsafe Cylinder

- [ ] Customer: "Report damaged or unsafe cylinder" option on active and completed orders
- [ ] `DamagedCylinderAction(order)`:
  - `has_issue = true`, `issue_type = damaged_cylinder`
  - Fire `DamagedCylinderReportedEvent` — **P0 priority, immediate**
  - Admin receives urgent notification (red alert, not dismissible until resolved)
  - Customer sees safety guidance immediately in-app
  - SOS button and shop phone number surfaced
  - Stock: tag the cylinder batch for inspection (log to `StockAuditLog`)
  - Rider: quality incident flag
- [ ] Write Feature tests — this is P0

**✅ Phase 9 Checkpoint:** All six exception scenarios are handled. Customer never needs to call the shop to resolve a standard failure. Admin receives actionable alerts for every exception.

---

## Phase 10 — Notification System

**Goal:** Every critical event sends both a push notification (in-app) and an SMS fallback.

### 10.1 SMS Service

- [ ] Create `SmsService` interface: `send(string $phone, string $message): bool`
- [ ] Implement `AfricasTalkingSmsService` using Africa's Talking SDK
- [ ] Implement `TwilioSmsService` as a fallback (configurable via `config/sms.php`)
- [ ] Bind the active implementation in `AppServiceProvider`
- [ ] All SMS dispatched via queued `SendSmsJob` (no blocking the request cycle)
- [ ] Log all SMS sends to `notifications_log`

### 10.2 In-App Notifications (Laravel Notifications)

- [ ] Create notification classes in `app/Notifications/Customer/`:
  - `OrderPlacedNotification`
  - `RiderAssignedNotification`
  - `RiderNearbyNotification`
  - `OrderDeliveredNotification`
  - `SafetyTipNotification` (10 mins post-delivery)
  - `GasPointsMilestoneNotification`
  - `ShopOpenNotification` (only for customers who tried to order while closed)
  - `ReferralConvertedNotification`
  - `OutOfStockNotification`
  - `OrderCancelledNotification`
- [ ] Each notification implements both `toBroadcast()` (WebSocket) and `toSms()` channels
- [ ] Unread notification count displayed in customer header

### 10.3 WebSocket with Laravel Reverb

- [ ] Install and configure Laravel Reverb: `composer require laravel/reverb`
- [ ] Publish Reverb config and start server
- [ ] Install Laravel Echo + Pusher JS: `npm install laravel-echo pusher-js`
- [ ] Configure Echo in `resources/js/bootstrap.ts`
- [ ] Create broadcasting channels in `routes/channels.php`:
  - `orders.{orderId}` — private, customer + admin
  - `admin.orders` — private, admin only
  - `admin.stock` — private, admin only
- [ ] Broadcast events:
  - `OrderStatusChangedEvent` → `orders.{orderId}` channel
  - `NewOrderEvent` → `admin.orders` channel
  - `LowStockAlertEvent` → `admin.stock` channel
- [ ] Update `Tracking.tsx` to subscribe to WebSocket instead of polling
- [ ] Update Admin `Orders/Index.tsx` to subscribe to `admin.orders`
- [ ] Update Admin `Stock/Index.tsx` to subscribe to `admin.stock`

### 10.4 Scheduled Notifications

- [ ] Add to `app/Console/Kernel.php` (or `bootstrap/app.php` in Laravel 11):
  - `CheckRiderDelaysJob` — every 5 minutes
  - `CheckLowStockJob` — every 15 minutes
  - `SendShopOpenNotificationsJob` — daily at 7:00 AM (to customers who tried to order when closed)
  - `SendSafetyTipJob` — fires 10 minutes after each delivery (triggered by event, not cron)

**✅ Phase 10 Checkpoint:** All notification triggers are wired. Customers receive push and SMS for every order state change. Admin receives real-time alerts. WebSocket live tracking is functional.

---

## Phase 11 — GasPoints Loyalty Programme

**Goal:** The redesigned GasPoints system is fully functional — earning, redeeming, referrals, and milestones.

### 11.1 GasPoints Service

- [ ] Create `GasPointsService` with methods:
  - `award(customer, points, type, description, orderId = null): void`
  - `redeem(customer, points): bool` — checks balance, deducts, records transaction
  - `getBalance(customer): int`
  - `checkMilestones(customer): void` — checks if any milestone notifications should fire
- [ ] `award()` flow:
  1. Create `GasPointsTransaction` record
  2. Increment `customers.gaspoints_balance`
  3. Call `checkMilestones()`
- [ ] Milestone thresholds (configurable in `config/gaspoints.php`): 500, 1000, 2000, 5000

### 11.2 Earning GasPoints (Event Listeners)

- [ ] `RatingSubmittedListener` → award 25 points for review
- [ ] `OrderDeliveredListener` → award points based on order type:
  - New customer first order: +250 (welcome bonus) — check `customer.created_at` vs first order
  - Swap order: +100
  - New cylinder order: +150
  - 25kg or 50kg order: +200 (override above)
- [ ] `ReferralConvertedListener` → award 250 to referrer when friend places first order
- [ ] `ReferralThirdOrderListener` → award 100 bonus to referrer when friend's 3rd order is placed
- [ ] Track referral attribution: `customers.referred_by` links to referrer's ID

### 11.3 Referral System

- [ ] Generate unique `referral_code` on customer creation (8-character alphanumeric, unique)
- [ ] Referral code entry during onboarding (optional field after name entry)
- [ ] `ApplyReferralCodeAction(customer, code)`:
  - Find referrer by code
  - Set `customers.referred_by = referrer.id`
  - Cannot self-refer, cannot re-apply a code
- [ ] Track referral order count in `GasPointsService`

### 11.4 Redemption at Checkout

- [ ] Customer sees GasPoints balance on Review Order screen
- [ ] "Redeem X points for Ksh Y off" toggle (auto-selects best available tier)
- [ ] `ApplyGasPointsRedemptionAction`:
  - Validates customer has sufficient balance
  - Reduces order total
  - Deducts points at order placement (not delivery, to prevent gaming)
  - Creates `GasPointsTransaction` with `type = redeemed`
- [ ] If order is cancelled: refund redeemed points back to balance

### 11.5 Customer GasPoints Dashboard

- [ ] Build `resources/js/Pages/Customer/GasPoints/Index.tsx`:
  - Current balance (large, prominent)
  - Redemption tiers with progress bars
  - Transaction history (earned / redeemed entries)
  - Referral share section: unique code + share via WhatsApp/SMS buttons
  - Platinum badge display (if applicable)

**✅ Phase 11 Checkpoint:** GasPoints are earned correctly for all order types, redeemed at checkout, referral attribution works, and milestones fire notifications.

---

## Phase 12 — Safety Features

**Goal:** SOS button, safety checklist, post-delivery safety tip, and rider certification are all functional.

### 12.1 SOS Button

- [ ] Permanent SOS button in `CustomerLayout.tsx` (top-right, red shield icon)
- [ ] Build `resources/js/Components/Customer/SosButton.tsx`:
  - On tap: show confirmation dialog "Are you experiencing a gas emergency?"
  - On confirm: call `GET /customer/sos/trigger`
  - Client-side: call `tel:999` using `window.location.href`
- [ ] `SosController::trigger(request)`:
  - Log the SOS event with customer ID and GPS coordinates
  - Dispatch `SosTriggerAlert` to admin immediately (SMS + dashboard alert)
  - SMS to shop: "EMERGENCY — [Customer Name] at [map link] reported a gas emergency. Last order #XXXX"
- [ ] Admin dashboard: SOS alerts appear as a high-priority dismissible banner

### 12.2 Post-Delivery Safety Push Notification

- [ ] `SafetyTipJob` — dispatched with a 10-minute delay when `OrderDeliveredEvent` fires
- [ ] Message: "Safety Tip from EldoGas: Smell gas? Do not switch on lights. Open all windows, leave the building, and call 999 or 0800 723 723."
- [ ] Delivered via push (in-app notification) AND SMS

### 12.3 Rider Safety Checklist (via API — Phase 14)

- [ ] Define 5-item checklist in `config/safety_checklist.php`:
  - `cylinder_condition`, `valve_check`, `safe_handover`, `empty_collected`, `payment_confirmed`
- [ ] API endpoint: `POST /api/v1/rider/orders/{id}/checklist`
- [ ] All 5 items must be checked before `POST /api/v1/rider/orders/{id}/deliver` is accepted
- [ ] Checklist responses stored as JSON in `orders.safety_checklist` column (add migration)
- [ ] Admin can view the completed checklist on the Order detail page

### 12.4 Rider Safety Certification (Admin)

- [ ] Safety Certified badge shown on:
  - Live tracking screen for customers (from rider card data)
  - Admin rider management list
  - Rider's own profile (via future Rider App)
- [ ] Badge only displays if `riders.is_safety_certified = true` AND `certification_date` is within 12 months
- [ ] Schedule `CheckCertificationExpiryJob` (runs monthly) — flags expired certifications, alerts admin

**✅ Phase 12 Checkpoint:** SOS works end-to-end. Post-delivery safety tip sends reliably. Rider safety checklist blocks delivery confirmation. Certification expiry is tracked.

---

## Phase 13 — Admin: Analytics & Reporting

**Goal:** Admin has a data-rich dashboard for operational and revenue visibility.

### 13.1 Admin Dashboard Home

- [ ] Create `AdminDashboardController` — aggregates key metrics
- [ ] Build `resources/js/Pages/Admin/Dashboard/Index.tsx`:
  - **Metric cards:** Today's orders, Today's revenue, Active riders, Pending orders, Low-stock alerts
  - **Revenue chart:** Last 7 days bar chart (Cash vs M-Pesa split) using Recharts
  - **Orders by size:** Donut chart of order volume by cylinder size
  - **Recent orders:** Last 10 orders table with status badges
  - **Rider leaderboard:** Top 5 riders by deliveries this week
  - **Stock status:** Mini stock table with colour-coded status

### 13.2 Revenue Reports

- [ ] Build `resources/js/Pages/Admin/Reports/Revenue.tsx`:
  - Date range picker
  - Breakdown: orders count, revenue, by payment method, by cylinder size
  - Export to CSV via `php artisan` + Laravel Excel endpoint
- [ ] Create `RevenueReportExport` class using Laravel Excel

### 13.3 Order Reports

- [ ] Build `resources/js/Pages/Admin/Reports/Orders.tsx`:
  - Filterable by date, status, rider, size, order type
  - Exception rate column (% orders with issues)
  - Export to CSV

### 13.4 Customer Database

- [ ] Build `resources/js/Pages/Admin/Customers/Index.tsx`:
  - Search by name or phone
  - Columns: name, phone, join date, total orders, GasPoints balance, last order
  - Link to individual customer profile
- [ ] Build `resources/js/Pages/Admin/Customers/Show.tsx`:
  - Profile info
  - Order history
  - GasPoints transaction history
  - Saved addresses
  - Referral stats

**✅ Phase 13 Checkpoint:** Admin dashboard shows accurate real-time metrics. Revenue and order reports can be filtered and exported. Customer profiles are accessible.

---

## Phase 14 — REST API Layer (Flutter-Ready)

**Goal:** All business logic is also accessible via a versioned, authenticated REST API. This phase does not build the Flutter apps — it builds the API they will consume.

### 14.1 API Authentication

- [ ] Enable Laravel Sanctum for token-based auth
- [ ] Customer API auth: `POST /api/v1/auth/request-otp` and `POST /api/v1/auth/verify-otp` → returns `access_token`
- [ ] Rider API auth: `POST /api/v1/rider/auth/login` (phone + OTP) → returns `access_token`
- [ ] All protected routes require `Authorization: Bearer {token}` header
- [ ] Token expiry: 30 days for customer, 24 hours for rider (configurable)
- [ ] Create `EnsureApiCustomer` and `EnsureApiRider` middleware

### 14.2 Customer API Endpoints

```
POST   /api/v1/auth/request-otp
POST   /api/v1/auth/verify-otp
GET    /api/v1/customer/profile
PATCH  /api/v1/customer/profile
GET    /api/v1/customer/addresses
POST   /api/v1/customer/addresses
PATCH  /api/v1/customer/addresses/{id}
DELETE /api/v1/customer/addresses/{id}
GET    /api/v1/catalogue/sizes           — active sizes with stock status
GET    /api/v1/catalogue/sizes/{id}/brands
GET    /api/v1/catalogue/sizes/{id}/addons
GET    /api/v1/catalogue/sizes/{id}/prices
POST   /api/v1/orders                   — place order
GET    /api/v1/orders                   — order history
GET    /api/v1/orders/{id}              — order detail
GET    /api/v1/orders/{id}/tracking     — live tracking data
POST   /api/v1/orders/{id}/cancel
POST   /api/v1/orders/{id}/rate
POST   /api/v1/orders/{id}/report-issue
GET    /api/v1/gaspoints/balance
GET    /api/v1/gaspoints/transactions
POST   /api/v1/customer/sos
```

### 14.3 Rider API Endpoints

```
POST   /api/v1/rider/auth/login
GET    /api/v1/rider/profile
PATCH  /api/v1/rider/availability        — toggle online/offline
PATCH  /api/v1/rider/location            — update GPS coordinates
GET    /api/v1/rider/orders/incoming     — assigned order (pending acceptance)
POST   /api/v1/rider/orders/{id}/accept
POST   /api/v1/rider/orders/{id}/decline
POST   /api/v1/rider/orders/{id}/pickup  — triggers stock deduction
POST   /api/v1/rider/orders/{id}/deliver — requires completed checklist
POST   /api/v1/rider/orders/{id}/checklist
POST   /api/v1/rider/orders/{id}/report-issue
GET    /api/v1/rider/earnings
GET    /api/v1/rider/orders              — order history
```

### 14.4 API Resources & Response Format

- [ ] Create an `ApiResource` for every entity: `OrderResource`, `CylinderSizeResource`, `RiderResource`, etc.
- [ ] Standardise response envelope:
```json
{
  "success": true,
  "data": { ... },
  "message": "Order placed successfully",
  "meta": { "timestamp": "..." }
}
```
- [ ] Standardise error response:
```json
{
  "success": false,
  "error": { "code": "OUT_OF_STOCK", "message": "This size is currently unavailable" }
}
```
- [ ] Document all endpoints using Scribe (`knuckleswtf/scribe`): `php artisan scribe:generate`

**✅ Phase 14 Checkpoint:** All API endpoints return correct data. Postman collection is generated. Sanctum token authentication is working for both customer and rider. API documentation is accessible at `/docs`.

---

## Phase 15 — Testing, Security & Performance

**Goal:** Production-grade quality assurance before go-live.

### 15.1 Test Coverage

**Feature (HTTP) Tests — target 80%+ coverage:**
- [ ] `tests/Feature/Admin/AuthTest.php` — login, lockout, logout, password reset
- [ ] `tests/Feature/Admin/CatalogueTest.php` — CRUD for sizes, brands, prices, add-ons
- [ ] `tests/Feature/Admin/StockTest.php` — adjustments, deductions, alerts, auto-hide
- [ ] `tests/Feature/Admin/OrderManagementTest.php` — assign, reassign, cancel
- [ ] `tests/Feature/Customer/AuthTest.php` — OTP request, verify, rate limit
- [ ] `tests/Feature/Customer/OrderTest.php` — swap order, new cylinder, add-ons, out-of-stock
- [ ] `tests/Feature/Customer/ExceptionFlowTest.php` — all 6 exception scenarios
- [ ] `tests/Feature/Customer/GasPointsTest.php` — earn, redeem, referral, milestones
- [ ] `tests/Feature/Api/CustomerApiTest.php` — all customer API endpoints
- [ ] `tests/Feature/Api/RiderApiTest.php` — all rider API endpoints

**Unit Tests:**
- [ ] `GasPointsServiceTest` — all earning and redemption logic
- [ ] `StockServiceTest` — deduction, threshold, auto-hide
- [ ] `OtpServiceTest` — generation, expiry, rate limiting
- [ ] `PlaceOrderActionTest` — race condition handling, price snapshot
- [ ] `RiderDispatchServiceTest` — assignment logic

### 15.2 Security Hardening

- [ ] Enable CSRF protection on all web routes (Laravel default)
- [ ] Rate limit all authentication endpoints (OTP: 3/10min, Login: 5/min)
- [ ] Sanitize all user inputs — use `strip_tags()` on text fields
- [ ] Validate all file uploads: type whitelist, max size, virus scan (optional: ClamAV)
- [ ] Set `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options` headers via middleware
- [ ] Enable HTTPS-only by setting `FORCE_HTTPS=true` in production config
- [ ] Store secrets in `.env`, never in code — run `php artisan key:generate`
- [ ] Review all Eloquent queries for N+1 (use Laravel Telescope to detect during development)
- [ ] Add `eager loading` to all index controllers that display relationships
- [ ] Use `DB::transaction()` wrappers around multi-step database operations (order placement, stock deduction)
- [ ] Implement API rate limiting: 60 requests/minute for authenticated users, 10/minute for auth endpoints
- [ ] Audit all `$fillable` arrays — confirm no sensitive fields are mass-assignable

### 15.3 Performance

- [ ] Add database indexes to all foreign keys and frequently-queried columns (already in schema above)
- [ ] Cache the product catalogue (sizes, prices, brands) with a 5-minute Redis cache, invalidated on admin update
- [ ] Cache shop open/closed status
- [ ] Configure Laravel Horizon with appropriate queue workers per queue priority:
  - `high`: order placement, stock deduction, SOS alerts
  - `default`: notifications, GasPoints
  - `low`: SMS, report generation
- [ ] Paginate all list endpoints (10–25 records per page)
- [ ] Use `select()` to fetch only required columns in list queries
- [ ] Enable Vite code splitting and asset hashing for cache-busting
- [ ] Compress images with `spatie/laravel-image-optimizer`

**✅ Phase 15 Checkpoint:** Test suite passes. No critical security vulnerabilities. Page load times are under 2 seconds on a standard connection.

---

## Phase 16 — Deployment & DevOps

**Goal:** Production environment is stable, monitored, and deployable with a single command.

### 16.1 Server Setup

- [ ] Provision Ubuntu 22.04 LTS server (recommended: 4 vCPU, 8GB RAM minimum for production)
- [ ] Install PHP 8.2, Nginx, MySQL 8, Redis, Supervisor, Node.js 20
- [ ] Configure Nginx virtual hosts:
  - `app.eldogas.co.ke` → Customer Web
  - `admin.eldogas.co.ke` → Admin Portal
  - `api.eldogas.co.ke` → API (optional separate subdomain)
- [ ] Configure SSL certificates via Let's Encrypt (Certbot)
- [ ] Configure Supervisor to manage:
  - `php artisan queue:work --queue=high,default,low` (2 workers)
  - `php artisan reverb:start` (WebSocket server)
  - `php artisan horizon` (queue monitoring)
- [ ] Configure MySQL with proper character set (`utf8mb4`) and daily backups
- [ ] Set up Redis with persistence (`appendonly yes`)
- [ ] Set all production `.env` values:
  - `APP_ENV=production`, `APP_DEBUG=false`
  - `SESSION_SECURE_COOKIE=true`
  - `LOG_CHANNEL=stack`

### 16.2 Deployment Pipeline

- [ ] Create `deploy.sh` script:
  ```bash
  git pull origin main
  composer install --no-dev --optimize-autoloader
  php artisan migrate --force
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan event:cache
  npm ci && npm run build
  php artisan queue:restart
  php artisan reverb:restart
  ```
- [ ] Configure GitHub Actions `deploy.yml` workflow:
  - Trigger: push to `main` branch
  - Steps: run tests → SSH deploy to server
- [ ] Set up zero-downtime deployments using Laravel Forge atomic deployments or Envoy

### 16.3 Monitoring & Logging

- [ ] Configure Laravel Telescope for local development only (`APP_ENV=local`)
- [ ] Configure Laravel Horizon for production queue monitoring
- [ ] Set up log rotation: `storage/logs/laravel.log` — daily, keep 14 days
- [ ] Configure error alerting: send errors to admin email or Slack webhook via `LOG_SLACK_WEBHOOK_URL`
- [ ] Set up uptime monitoring (UptimeRobot or similar) on all three subdomains
- [ ] Configure database slow query log (queries > 1 second)

### 16.4 Go-Live Checklist

- [ ] All migrations run successfully on production database
- [ ] Admin seeder run: first super admin created
- [ ] All 5 cylinder sizes seeded with real prices (entered by Admin before launch)
- [ ] All gas brands seeded
- [ ] All add-on groups and items seeded
- [ ] Stock counts entered for all active sizes
- [ ] Shop open/closed status set to `open`
- [ ] SMS service tested with a real Kenyan phone number
- [ ] OTP delivery confirmed working
- [ ] End-to-end order placement tested on production
- [ ] M-Pesa till number verified and displayed correctly
- [ ] SOS button tested
- [ ] SSL certificates active on all subdomains
- [ ] Queue workers confirmed running via Supervisor
- [ ] WebSocket server running via Supervisor

**✅ Phase 16 Checkpoint:** Production environment is live, monitored, and deployed via CI/CD.

---

## Appendix A — Directory Structure

```
eldogas/
├── app/
│   ├── Actions/                    # Single-responsibility action classes
│   │   ├── Order/
│   │   │   ├── PlaceOrderAction.php
│   │   │   ├── CancelOrderAction.php
│   │   │   └── WrongCylinderAction.php
│   │   └── GasPoints/
│   │       ├── AwardGasPointsAction.php
│   │       └── RedeemGasPointsAction.php
│   ├── Events/                     # Domain events
│   │   ├── Order/
│   │   │   ├── OrderPlacedEvent.php
│   │   │   ├── OrderDeliveredEvent.php
│   │   │   └── OrderCancelledEvent.php
│   │   ├── Stock/
│   │   │   ├── LowStockAlertEvent.php
│   │   │   └── StockDepletedEvent.php
│   │   └── Safety/
│   │       └── SosTriggerEvent.php
│   ├── Exceptions/
│   │   ├── OutOfStockException.php
│   │   └── InsufficientGasPointsException.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/
│   │   │   │   ├── Auth/
│   │   │   │   ├── Catalogue/
│   │   │   │   ├── Orders/
│   │   │   │   ├── Riders/
│   │   │   │   ├── Stock/
│   │   │   │   ├── Customers/
│   │   │   │   └── Reports/
│   │   │   ├── Customer/
│   │   │   │   ├── Auth/
│   │   │   │   ├── Order/
│   │   │   │   ├── GasPoints/
│   │   │   │   └── Profile/
│   │   │   └── Api/
│   │   │       └── V1/
│   │   │           ├── Customer/
│   │   │           └── Rider/
│   │   ├── Middleware/
│   │   ├── Requests/               # Form Request validation classes
│   │   │   ├── Admin/
│   │   │   └── Customer/
│   │   └── Resources/              # API Resource classes
│   │       ├── OrderResource.php
│   │       └── CylinderSizeResource.php
│   ├── Jobs/
│   │   ├── SendSmsJob.php
│   │   ├── CheckRiderDelaysJob.php
│   │   ├── CheckLowStockJob.php
│   │   └── SafetyTipJob.php
│   ├── Listeners/
│   │   ├── Order/
│   │   ├── Stock/
│   │   └── GasPoints/
│   ├── Models/
│   ├── Notifications/
│   │   └── Customer/
│   ├── Policies/
│   └── Services/
│       ├── OtpService.php
│       ├── OrderService.php
│       ├── StockService.php
│       ├── GasPointsService.php
│       ├── RiderDispatchService.php
│       ├── NotificationService.php
│       └── Sms/
│           ├── SmsServiceInterface.php
│           └── AfricasTalkingSmsService.php
│
├── resources/
│   └── js/
│       ├── Components/
│       │   ├── Admin/
│       │   ├── Customer/
│       │   └── Shared/
│       ├── Layouts/
│       │   ├── AdminLayout.tsx
│       │   ├── CustomerLayout.tsx
│       │   └── GuestLayout.tsx
│       ├── Pages/
│       │   ├── Admin/
│       │   │   ├── Auth/
│       │   │   ├── Catalogue/
│       │   │   ├── Customers/
│       │   │   ├── Dashboard/
│       │   │   ├── Orders/
│       │   │   ├── Riders/
│       │   │   ├── Reports/
│       │   │   └── Stock/
│       │   └── Customer/
│       │       ├── Auth/
│       │       ├── GasPoints/
│       │       ├── Onboarding/
│       │       ├── Order/
│       │       └── Profile/
│       ├── hooks/                  # Custom React hooks
│       │   ├── useOrder.ts
│       │   └── useGasPoints.ts
│       ├── types/                  # TypeScript interfaces
│       │   ├── models.ts
│       │   └── inertia.d.ts
│       └── lib/
│           ├── utils.ts
│           └── formatters.ts       # KES formatting, date formatting
│
├── routes/
│   ├── web.php
│   ├── admin.php
│   ├── customer.php
│   ├── api.php
│   └── channels.php
│
└── tests/
    ├── Feature/
    │   ├── Admin/
    │   ├── Customer/
    │   └── Api/
    └── Unit/
        └── Services/
```

---

## Appendix B — Naming Conventions

| Item | Convention | Example |
|---|---|---|
| Models | PascalCase singular | `CylinderSize` |
| Controllers | PascalCase + Controller | `CylinderSizeController` |
| Actions | PascalCase + Action | `PlaceOrderAction` |
| Services | PascalCase + Service | `GasPointsService` |
| Events | PascalCase + Event | `OrderPlacedEvent` |
| Listeners | PascalCase + Listener | `AwardGasPointsListener` |
| Jobs | PascalCase + Job | `SendSmsJob` |
| Requests | PascalCase + Request | `PlaceOrderRequest` |
| Migrations | snake_case, descriptive | `create_order_addons_table` |
| React components | PascalCase | `OrderStatusBadge.tsx` |
| React pages | PascalCase, in Pages/ | `Pages/Customer/Order/Tracking.tsx` |
| CSS classes | Tailwind utility classes only | No custom CSS files |
| API routes | kebab-case, versioned | `/api/v1/cylinder-sizes` |
| Web routes | kebab-case | `/admin/cylinder-sizes` |
| Route names | dot-notation | `admin.catalogue.sizes.index` |
| DB columns | snake_case | `gas_refill_price` |
| DB tables | snake_case, plural | `cylinder_sizes` |
| Env vars | SCREAMING_SNAKE_CASE | `MPESA_TILL_NUMBER` |

---

## Appendix C — Environment Variables

```dotenv
# Application
APP_NAME="EldoGas"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost
APP_ADMIN_URL=http://localhost/admin

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=eldogas
DB_USERNAME=root
DB_PASSWORD=

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Queue & Cache
QUEUE_CONNECTION=redis
CACHE_STORE=redis

# Broadcasting (Reverb)
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=localhost
REVERB_PORT=8080

# SMS — Africa's Talking
AT_API_KEY=
AT_USERNAME=
AT_SENDER_ID=EldoGas

# M-Pesa
MPESA_ENVIRONMENT=sandbox
MPESA_CONSUMER_KEY=
MPESA_CONSUMER_SECRET=
MPESA_SHORTCODE=
MPESA_PASSKEY=
MPESA_TILL_NUMBER=
MPESA_CALLBACK_URL=

# Mail (admin password reset)
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@eldogas.co.ke
MAIL_FROM_NAME="EldoGas"

# Shop Settings
SHOP_OPENING_HOUR=7
SHOP_CLOSING_HOUR=21
SHOP_TIMEZONE=Africa/Nairobi
```

---

## Development Phase Summary

| Phase | Module | Priority | Estimated Effort |
|---|---|---|---|
| 1 | Foundation & Infrastructure | P0 | 1 week |
| 2 | Admin: Authentication | P0 | 3 days |
| 3 | Admin: Product Catalogue | P0 | 1 week |
| 4 | Admin: Stock Management | P0 | 4 days |
| 5 | Admin: Rider Management | P0 | 4 days |
| 6 | Customer: Auth & Onboarding | P0 | 1 week |
| 7 | Customer: Ordering System | P0 | 2 weeks |
| 8 | Admin: Order Management | P0 | 1 week |
| 9 | Exception Flows | P0 | 1 week |
| 10 | Notification System | P1 | 1 week |
| 11 | GasPoints Loyalty | P1 | 1 week |
| 12 | Safety Features | P1 | 4 days |
| 13 | Admin: Analytics & Reports | P1 | 1 week |
| 14 | REST API Layer | P1 | 1.5 weeks |
| 15 | Testing, Security & Performance | P1 | 1 week |
| 16 | Deployment & DevOps | P0 | 4 days |
| **Total** | | | **~16–18 weeks** |

> **Note on sequencing:** Phases 1–9 form the critical path to a working MVP. Phases 10–13 can be developed in parallel by a second developer once Phase 7 is complete. Phase 14 should be built incrementally alongside Phases 6–9, as the service layer is shared between web controllers and API controllers.

---

*EldoGas — Gas delivered. No stress.*  
*Development Plan · Confidential*
