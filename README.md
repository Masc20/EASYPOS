<div align="center">

# EASYPOS &mdash; Centralized Multi-Branch POS & Business Intelligence System

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-3.x-4E56A6?style=for-the-badge&logo=livewire&logoColor=white)](https://livewire.laravel.com)
[![Filament](https://img.shields.io/badge/Filament-4.x-F59E0B?style=for-the-badge&logo=filament&logoColor=white)](https://filamentphp.com)
[![TailwindCSS](https://img.shields.io/badge/TailwindCSS-v4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Spatie Permissions](https://img.shields.io/badge/Spatie-RBAC-blue?style=for-the-badge)](https://spatie.be/docs/laravel-permission)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![PHPUnit Tests](https://img.shields.io/badge/PHPUnit-104%20Tests%20%7C%20397%20Assertions%20Passed-brightgreen?style=for-the-badge&logo=checkmarx&logoColor=white)](tests/)
[![BIR Compliance](https://img.shields.io/badge/BIR%20Tax-RR%2016--2005%20Compliant-emerald?style=for-the-badge)](https://www.bir.gov.ph)

</div>

**EASYPOS** is an enterprise-grade Point of Sale (POS), Kitchen Display System (KDS), and Multi-Branch Sales Intelligence platform engineered for modern restaurant and retail chains. Built using a **Domain-Driven Design (DDD) Modular Monolith** architecture, EASYPOS couples a zero-latency **Floor Staff Touch Terminal** (Employee ID + 4-digit PIN authentication) with a powerful **Filament Back-Office Backstage** (Email + Password authentication) for multi-location administration, real-time sales velocity monitoring, and official Philippine Bureau of Internal Revenue (BIR) tax reconciliation.

---

## Table of Contents

- [Architectural Philosophy](#architectural-philosophy)
- [System Architecture & Data Flow](#system-architecture--data-flow)
- [Key Domain Modules](#key-domain-modules)
  - [1. Point of Sale (POS Terminal)](#1-point-of-sale-pos-terminal)
  - [2. Kitchen Display System (KDS)](#2-kitchen-display-system-kds)
  - [3. Menu & Recipe Management](#3-menu--recipe-management)
  - [4. Smart Sales & Revenue Dashboard (Module 8 / SCRUM-175)](#4-smart-sales--revenue-dashboard-module-8--scrum-175)
  - [5. Sales Records, Financial Ledger & BIR Auditing (SCRUM-147 / SCRUM-171)](#5-sales-records-financial-ledger--bir-auditing-scrum-147--scrum-171)
  - [6. Multi-Branch Operations & Staff Governance](#6-multi-branch-operations--staff-governance)
- [Role-Based Access Control (RBAC) & Station Routing](#role-based-access-control-rbac--station-routing)
- [Default Seeded Credentials](#default-seeded-credentials)
- [Directory Structure](#directory-structure)
- [Getting Started & Local Installation](#getting-started--local-installation)
- [Automated Testing & Verification](#automated-testing--verification)
- [UI Design Tokens & Print Rules](#ui-design-tokens--print-rules)

---

## Architectural Philosophy

EASYPOS implements a **Modular Monolith** pattern organized strictly into isolated domain namespaces under `app/Domains/`. Each domain encapsulates its own Models, Actions, Events, Services, and Livewire components:

1. **Strict Decoupling of Operational Velocity & Financial Auditing**:
   - Executive Business Intelligence (KPI metrics, revenue trend lines, peak sales rush histograms, and best-seller leaderboards) lives on the **Smart Dashboard** (`/admin`).
   - Cash register drawer balancing, cashier terminal performance, BIR tax compliance breakdown, tender reconciliation, and periodic audit exports live in **Sales Records** (`/admin/sales-records`).
2. **0ms Client-Side Responsiveness**:
   - Floor terminals utilize Alpine.js reactive states for instant cart counter increments, discount applications, tender changes, and modal openings without server roundtrips.
3. **Pure Utility Tailwind CSS**:
   - The entire platform conforms to utility-first styling with Tailwind CSS v4 tokens. Dedicated `@media print` directives are strictly isolated to physical print views (80mm thermal receipt slips and A4 BIR audit sheets).
4. **Resilient Financial Calculations**:
   - Dual-compatibility aggregation engine guarantees accurate cross-database decimal arithmetic across SQLite, MySQL 8.0+, and PostgreSQL.

---

## System Architecture & Data Flow

```mermaid
flowchart TD
    subgraph FloorStaff["Floor Staff Touch Terminal (Port 8000)"]
        PINPad["PIN Keypad Login<br/>(/login)"] --> AuthCheck{"Role & Station Gate"}
        AuthCheck -->|Cashier| POSStation["POS Checkout Terminal<br/>(/pos)"]
        AuthCheck -->|Cook| KDSStation["Kitchen Display Board<br/>(/kitchen)"]
        AuthCheck -->|Chef| MenuStation["Chef's Menu Catalog<br/>(/menu)"]
    end

    subgraph POSFlow["POS Checkout Engine"]
        POSStation --> Cart["Alpine.js Zero-Lag Cart"]
        Cart --> Settlement["Multi-Tender Modal<br/>(Cash / Card / Digital QR)"]
        Settlement --> CreateOrder["Transactional Order Creation"]
        CreateOrder --> ThermalPrint["80mm Thermal Receipt Slip<br/>(BIR Compliant)"]
        CreateOrder --> DispatchKDS["Automatic KDS Dispatch"]
    end

    subgraph KDSFlow["Live Kitchen Prep Queue"]
        DispatchKDS --> Ticket["Sequential Cook Ticket<br/>(e.g., K-001)"]
        Ticket --> PrepLifecycle["in_prep &rarr; ready &rarr; served"]
        PrepLifecycle --> ItemCheck["Individual Dish Checkoffs"]
    end

    subgraph BackOffice["Back-Office Administrative Portal (/admin)"]
        AdminAuth["Email + Password Gate"] --> AdminRole{"Permissions Check"}
        AdminRole --> SmartDashboard["Smart Sales & Revenue Dashboard<br/>- Executive KPIs & Rush Alerts<br/>- Daily Revenue Trend Line<br/>- Peak Sales Hours Histogram<br/>- Best-Sellers Leaderboard"]
        AdminRole --> SalesRecords["Sales Records & Financial Ledger<br/>- Cash Register Drawer Balancing<br/>- BIR RR 16-2005 Tax Breakdown<br/>- Tender Reconciliation Doughnut<br/>- Periodic CSV & A4 Audit Report"]
        AdminRole --> FilamentCRUD["Filament Management Resources<br/>- Meals & Categories Catalog<br/>- Transaction History & Receipts<br/>- Staff & RBAC Permissions"]
    end

    subgraph Database["Relational Storage & Aggregation Engine"]
        SalesService["SalesAggregationService"]
        CreateOrder --> Database
        SmartDashboard --> SalesService
        SalesRecords --> SalesService
        SalesService --> Database
    end
```

---

## Key Domain Modules

### 1. Point of Sale (POS Terminal)

- **Zero-Latency Client Interaction**: Real-time dish searching, barcode scanning, quantity alterations, and dining mode switching (Dine-In vs Takeout) execute instantly on the client via Alpine.js.
- **Component Specialization**: Clean separation between [`ProductSearch.php`](file:///C:/xampp/htdocs/EASYPOS/app/Domains/PointOfSale/Livewire/ProductSearch.php) (barcode scanning and real-time search) and [`MealCatalog.php`](file:///C:/xampp/htdocs/EASYPOS/app/Domains/PointOfSale/Livewire/MealCatalog.php) (category tabs and reactive dish cards).
- **Multi-Tender Payment Settlement**: Supports Cash, Credit/Debit Card, and GCash/Maya QR payments with automated change calculation and reference verification.
- **Philippine BIR Tax Accessors**: Model-level accessors on [`Order.php`](file:///C:/xampp/htdocs/EASYPOS/app/Domains/PointOfSale/Models/Order.php) compliant with BIR Revenue Regulations:
  - **VATable Sales (12%)**: Sales amount net of 12% Value Added Tax.
  - **12% VAT Amount**: Explicit tax line calculation.
  - **VAT-Exempt Sales**: Non-taxable transactions (Senior Citizen / PWD purchases).
  - **Zero-Rated Sales**: 0% export/special economic zone sales.
- **80mm Thermal Receipt Printing**: Physical print layout isolated with `@media print` directives in [`receipt-slip.blade.php`](file:///C:/xampp/htdocs/EASYPOS/resources/views/components/pos/receipt-slip.blade.php) containing registered Store TIN, MIN, Serial Number, Cashier ID, and Senior/PWD customer acknowledgment lines.
- **Automatic Kitchen Handoff**: Finalizing payment dispatches a sequential kitchen ticket (`K-001`, `K-002`) and initiates cook line-item tracking.

### 2. Kitchen Display System (KDS)

- **Sequential Cook Queue**: Orders are tracked in real-time through `new` &rarr; `in_prep` &rarr; `ready` &rarr; `served`.
- **Granular Line-Item Preparation**: Cooks can individually check off dishes as they finish plating; when all items are marked done, the entire ticket automatically transitions to `ready`.
- **Rush Priority Engine**: One-click Rush Order prioritization with pulsating visual flags and audio chimes for floor staff runners.
- **Recall Capability**: Accidental bumps can be recalled back to the active prep line within the recall grace period.
- **Dining Filter Tabs**: Toggle between All, Dine-In, or Takeout tickets on the fly.

### 3. Menu & Recipe Management

- **Catalog Administration**: Paginated dish catalog with real-time category filtering, search, pricing, portion cost tracking, and automatic profit margin calculations.
- **Instant Availability Toggling**: One-click toggling of dish stock status (`is_available`), automatically disabling or hiding 86'd items on the POS floor in real time.
- **Portion Tracking & Depletion**: Tracks available inventory counts and warns cashiers when dishes reach low threshold.

### 4. Smart Sales & Revenue Dashboard (Module 8 / SCRUM-175)

Mounted as the default administrative landing page at `/admin` via [`App\Filament\Pages\Dashboard`](file:///C:/xampp/htdocs/EASYPOS/app/Filament/Pages/Dashboard.php):
- **Executive Timeframe & Branch Filter**: One-click presets (*Today*, *Yesterday*, *This Week*, *This Month*, *This Year*, *All Time*), date range selectors, and multi-location branch scoping.
- **6 High-Impact Executive KPI Cards**:
  1. **Gross Sales**: Total revenue before deductions.
  2. **Net Revenue**: Realized revenue after discounts and returns.
  3. **Completed Orders**: Total transaction count.
  4. **Average Order Value (AOV)**: Average customer basket spend.
  5. **Total Discounts**: Aggregate promotional, Senior Citizen, and PWD price deductions.
  6. **BIR Tax Collected**: 12% output VAT liability.
- **Peak Sales Rush Velocity Banner**: Algorithmic rush-detection engine displaying peak operational hour windows with dedicated status pills for **Lunch Rush (11 AM – 2 PM)** and **Dinner Rush (5 PM – 9 PM)**.
- **Visual Analytics Suite**:
  - [`DailySalesRevenueChart`](file:///C:/xampp/htdocs/EASYPOS/app/Filament/Widgets/DailySalesRevenueChart.php): Revenue trend line chart.
  - [`PeakSalesHoursChart`](file:///C:/xampp/htdocs/EASYPOS/app/Filament/Widgets/PeakSalesHoursChart.php): 24-hour transaction volume histogram.
- **Best-Selling Menu Items Leaderboard**: Dynamic ranking table with Gold (#1), Silver (#2), and Bronze (#3) podium medals, sort toggles for **By Volume** vs **By Revenue**, category filter chips, and row limit controls.
- **Category Performance Bars**: Proportional contribution bars breaking down volume and sales across menu categories.

### 5. Sales Records, Financial Ledger & BIR Auditing (SCRUM-147 / SCRUM-171)

Located at `/admin/sales-records` via [`App\Filament\Pages\SalesRecords`](file:///C:/xampp/htdocs/EASYPOS/app/Filament/Pages/SalesRecords.php):
- **Cash Register Drawer Balancing**: Calculates expected cash on hand, opening float balancing, and variance tracking.
- **Official BIR Tax Compliance Card**: Conforms strictly to Philippine Bureau of Internal Revenue regulations (RR 16-2005) detailing Vatable Sales, 12% VAT, VAT-Exempt Sales, and Net Tax Due.
- **Payment Tender Reconciliation**: Comprehensive tender ledger paired with [`PaymentMethodsDistributionChart`](file:///C:/xampp/htdocs/EASYPOS/app/Filament/Widgets/PaymentMethodsDistributionChart.php) doughnut visualization (Cash, Card, QR Transfers).
- **Staff Terminal Audit**: Operational breakdown attributing completed orders, gross intake, net intake, and ticket sizes per cashier station.
- **Dining Channel Distribution**: Visual progress indicators tracking Dine-In versus Takeout transactions.
- **Periodic Sales Reports**: Dynamic aggregations across Daily, Weekly, Monthly, and Yearly intervals.
- **Streamed CSV Export**: Instant download of periodic audit figures formatted for spreadsheet accounting.
- **Printable A4 BIR Audit Report**: Accessible at `/admin/sales-records/audit-report` via [`periodic-sales-audit.blade.php`](file:///C:/xampp/htdocs/EASYPOS/resources/views/reports/periodic-sales-audit.blade.php) featuring standard A4 print margins and official tax summary formatting.

### 6. Multi-Branch Operations & Staff Governance

- **Multi-Branch Data Scoping**: Segment menus, inventory, cashiers, and sales ledgers across independent physical store branches.
- **Centralized Transaction Audit**: Accessible via [`OrderResource`](file:///C:/xampp/htdocs/EASYPOS/app/Filament/Resources/Orders/OrderResource.php) allowing back-office supervisors to inspect historical orders, review payment methods, and reprint thermal receipts.
- **Staff Onboarding & Security**: Accessible via [`UserResource`](file:///C:/xampp/htdocs/EASYPOS/app/Filament/Resources/Users/UserResource.php) featuring automated Employee ID generation, 4-digit PIN setup, branch assignment, and role configuration.

---

## Role-Based Access Control (RBAC) & Station Routing

Every staff member is assigned an Employee ID, a hashed 4-digit PIN, an assigned branch, and a dedicated station. Staff attempting to access unauthorized endpoints receive an immediate `403 Forbidden` response:

| Role | Auth Method | Default Station | Accessible Routes | Primary Permissions |
| :--- | :--- | :--- | :--- | :--- |
| **Cashier** | Emp ID + PIN | POS Terminal | `/pos` | `pos.access`, `pos.checkout` |
| **Cook** | Emp ID + PIN | Kitchen Display | `/kitchen` | `kitchen.view`, `kitchen.prepare` |
| **Chef** | Emp ID + PIN | Chef's Menu Station | `/menu`, `/kitchen` | `menu.view`, `menu.manage`, `kitchen.view` |
| **Branch Manager** | Email + Pass / PIN | POS & Management | `/pos`, `/admin` | `admin.access`, `pos.access`, `menu.view`, `branch.manage` |
| **System Owner** | Email + Password | Executive Portal | `/admin` | *All Permissions (Super Admin)* |

---

## Default Seeded Credentials

Run `php artisan db:seed` to populate pre-configured test accounts across all roles:

### 1. Floor Staff Touch Terminal
> Sign in at [http://localhost:8000/login](http://localhost:8000/login)

| Role | Employee ID | PIN | Quick-Fill Shortcut |
| :--- | :--- | :--- | :--- |
| **Cashier** | `B01-CSH-001` | `1234` | Available on login screen |
| **Chef** | `B01-CHF-001` | `2345` | Available on login screen |
| **Cook** | `B01-COK-001` | `3456` | Available on login screen |
| **Branch Manager** | `B01-MGR-001` | `9999` | Available on login screen |

### 2. Back-Office Administrative Portal
> Sign in at [http://localhost:8000/admin/login](http://localhost:8000/admin/login)

| Role | Email | Password |
| :--- | :--- | :--- |
| **System Owner (Super Admin)** | `owner@easypos.com` | `password` |
| **Admin** | `admin@easypos.com` | `password` |
| **Branch Manager** | `mgr-b01@easypos.com` | `password` |

---

## Directory Structure

```text
EASYPOS/
├── app/
│   ├── Domains/                                      # Modular Monolith Business Domains
│   │   ├── Branches/                                 # Store & Multi-Branch Domain
│   │   │   └── Models/Branch.php
│   │   ├── Identity/                                 # Authentication, Staff & RBAC
│   │   │   ├── Actions/CreateUser.php
│   │   │   ├── Events/UserLoggedIn.php
│   │   │   ├── Livewire/Auth/Login.php               # Touchscreen PIN Pad Controller
│   │   │   └── Models/User.php                       # Staff profiles & station routes
│   │   ├── Kitchen/                                  # Kitchen Display System (KDS)
│   │   │   ├── Actions/                              # Ticket claiming, item status & bumps
│   │   │   ├── Events/KitchenTicketStatusUpdated.php
│   │   │   ├── Livewire/KitchenDisplay.php           # 3-column live prep queue
│   │   │   └── Models/                               # KitchenTicket.php, KitchenTicketItem.php
│   │   ├── Menu/                                     # Catalog, Recipes & Availability
│   │   │   ├── Actions/ToggleMealAvailabilityAction.php
│   │   │   ├── Livewire/MealCatalogTable.php
│   │   │   └── Models/                               # Meal.php, MealCategory.php
│   │   └── PointOfSale/                              # Checkout, Orders & Sales Engine
│   │       ├── Actions/CreateOrderAction.php
│   │       ├── Actions/ProcessPaymentAction.php
│   │       ├── Livewire/Checkout/CheckoutCart.php    # Alpine.js zero-latency cart
│   │       ├── Livewire/Checkout/CheckoutModal.php   # Payment tender processing
│   │       ├── Livewire/MealCatalog.php
│   │       ├── Livewire/ProductSearch.php            # Barcode scanner input
│   │       ├── Models/                               # Order.php, OrderItem.php, Payment.php
│   │       └── Services/
│   │           └── SalesAggregationService.php       # Enterprise sales analytics service
│   ├── Filament/                                     # Back-Office Admin Portal (/admin)
│   │   ├── Pages/
│   │   │   ├── Dashboard.php                         # Module 8 Smart Sales Dashboard
│   │   │   └── SalesRecords.php                      # Financial ledger & BIR compliance
│   │   ├── Resources/
│   │   │   ├── MealCategories/MealCategoryResource.php
│   │   │   ├── Meals/MealResource.php
│   │   │   ├── Orders/OrderResource.php              # Order audit & receipt reprints
│   │   │   └── Users/UserResource.php                # Staff accounts & PIN management
│   │   └── Widgets/
│   │       ├── DailySalesRevenueChart.php            # Revenue trend line chart
│   │       ├── PaymentMethodsDistributionChart.php   # Tender distribution doughnut
│   │       └── PeakSalesHoursChart.php               # Hourly sales volume histogram
│   ├── Livewire/Forms/                               # Shared reusable smart form widgets
│   └── Providers/
│       ├── AppServiceProvider.php
│       └── Filament/AdminPanelProvider.php           # Panel provider discovery & themes
├── database/
│   ├── migrations/                                   # Version-controlled schema migrations
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── MenuSeeder.php                            # Default restaurant catalog & dishes
│       └── RolesAndPermissionsSeeder.php             # Roles, permissions & default users
├── resources/
│   ├── css/app.css                                   # Tailwind v4 theme variables
│   ├── js/
│   │   ├── app.js                                    # Frontend entry point
│   │   ├── components/pin-pad.js                     # Zero-lag Alpine PIN pad controller
│   │   └── modules/theme.js                          # Dark/Light theme engine
│   └── views/
│       ├── components/
│       │   ├── pos/receipt-slip.blade.php            # 80mm BIR-compliant receipt slip
│       │   └── ui/                                   # High-density UI primitives (<x-ui.*>)
│       ├── filament/pages/
│       │   ├── dashboard.blade.php                   # Smart Sales Dashboard Blade
│       │   └── sales-records.blade.php               # Sales Records & Ledger Blade
│       ├── pages/                                    # Route destination views (/pos, /kitchen)
│       └── reports/
│           └── periodic-sales-audit.blade.php        # Printable A4 BIR audit report
├── routes/
│   ├── auth.php                                      # Floor staff PIN auth routes
│   └── web.php                                       # Station routes & audit report route
└── tests/
    └── Feature/                                      # 11 Comprehensive Feature Test Suites
```

---

## Getting Started & Local Installation

### Prerequisites

- **PHP 8.2 or later** (extensions required: `pdo_mysql` or `pdo_sqlite`, `mbstring`, `openssl`, `curl`, `intl`).
- **Composer 2.x**.
- **Node.js 20+** and `npm`.
- **MySQL 8.0+**, **MariaDB 10.4+**, or **SQLite 3**.

### Installation Steps

1. **Clone the repository**:
   ```powershell
   git clone <repository-url> EASYPOS
   cd EASYPOS
   ```

2. **Install PHP and JavaScript dependencies**:
   ```powershell
   composer install
   npm install
   ```

3. **Configure Environment Variables**:
   ```powershell
   Copy-Item .env.example .env
   php artisan key:generate
   ```

4. **Configure Database Connection**:
   Update `.env` with your database credentials:
   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=easypos
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. **Run Migrations & Seeders**:
   ```powershell
   php artisan migrate:fresh --seed
   ```

6. **Compile Frontend Assets**:
   ```powershell
   npm run build
   ```

7. **Start the Application**:
   In your primary terminal:
   ```powershell
   php artisan serve
   ```
   In a secondary terminal for hot-reloading asset development:
   ```powershell
   npm run dev
   ```

- **Floor Terminal**: [http://localhost:8000/login](http://localhost:8000/login)
- **Administrative Portal**: [http://localhost:8000/admin](http://localhost:8000/admin)

---

## Automated Testing & Verification

EASYPOS includes an enterprise-grade automated test suite executed with PHPUnit:

```powershell
php vendor/bin/phpunit
```

### Complete Test Coverage Summary (104 Tests, 397 Assertions &mdash; 100% Passed)

| Test Suite | Tests | Assertions | Key Business Behaviors Verified |
| :--- | :---: | :---: | :--- |
| [`Sprint3AnalyticsTest.php`](file:///C:/xampp/htdocs/EASYPOS/tests/Feature/Sprint3AnalyticsTest.php) | **4** | **79** | Best-selling rankings by volume/revenue, category filtering, periodic report calculation, hourly distribution, peak rush heuristics, Livewire Dashboard interactions, and printable A4 report route. |
| [`SalesRecordsTest.php`](file:///C:/xampp/htdocs/EASYPOS/tests/Feature/SalesRecordsTest.php) | **7** | **36** | Permission gates, financial summary calculations, tender distribution, cash drawer balancing, CSV export headers, and embedded chart widgets. |
| [`PointOfSaleTest.php`](file:///C:/xampp/htdocs/EASYPOS/tests/Feature/PointOfSaleTest.php) | **10** | **30** | POS terminal rendering, catalog search propagation, transactional order creation, portion decrements, tender change calculations, and BIR tax accessors. |
| [`KitchenTest.php`](file:///C:/xampp/htdocs/EASYPOS/tests/Feature/KitchenTest.php) | **10** | **35** | Cook ticket claiming, item status checkoffs, bumping to served, ticket recalls, rush order toggling, and dining type filtering. |
| [`AuthTest.php`](file:///C:/xampp/htdocs/EASYPOS/tests/Feature/AuthTest.php) | **9** | **28** | Floor staff PIN authentication, auto-submit keypad logic, lockout rate limiting, and theme cookie persistence. |
| [`AuthorizationTest.php`](file:///C:/xampp/htdocs/EASYPOS/tests/Feature/AuthorizationTest.php) | **9** | **27** | Strict station routing, automatic redirections from `/`, 403 enforcement, and navigation link isolation. |
| [`MenuDatabaseTest.php`](file:///C:/xampp/htdocs/EASYPOS/tests/Feature/MenuDatabaseTest.php) | **12** | **42** | Meal CRUD actions, soft deletes, availability toggling, and category relations. |
| [`UserManagementTest.php`](file:///C:/xampp/htdocs/EASYPOS/tests/Feature/UserManagementTest.php) | **11** | **38** | Back-office staff management, Employee ID generation, PIN hashing, branch assignment, and permission boundaries. |
| [`TransactionHistoryTest.php`](file:///C:/xampp/htdocs/EASYPOS/tests/Feature/TransactionHistoryTest.php) | **10** | **34** | Order audit table, branch scoping, receipt modal inspection, and thermal receipt reprints. |
| [`FilamentPanelTest.php`](file:///C:/xampp/htdocs/EASYPOS/tests/Feature/FilamentPanelTest.php) | **9** | **25** | Administrative authentication gates and floor PIN session isolation. |
| [`ErrorPagesTest.php`](file:///C:/xampp/htdocs/EASYPOS/tests/Feature/ErrorPagesTest.php) | **12** | **22** | Branded HTTP exception pages (401, 403, 404, 419, 429, 500, 503) and dark mode styling. |
| **Total** | **104** | **397** | **100% Passing** |

---

## UI Design Tokens & Print Rules

- **High-Density Borders**: All interactive components use sharpened `rounded-md` or `rounded-lg` borders. Soft, bubbly `rounded-2xl` or `rounded-full` pills are avoided for core POS action buttons.
- **Theme Color Tokens**: Fully supported Light and Dark mode using semantic tokens (`bg-brand-card`, `text-brand-text`, `border-brand-border`, `bg-brand-primary`).
- **Utility CSS Standards**: Blade templates strictly use pure Tailwind CSS classes. No inline `<style>` blocks are permitted in core application views.
- **Print View Isolation**:
  - **80mm Thermal Receipt** ([`receipt-slip.blade.php`](file:///C:/xampp/htdocs/EASYPOS/resources/views/components/pos/receipt-slip.blade.php)): Constrained to physical POS roll width with background elements hidden.
  - **A4 BIR Audit Report** ([`periodic-sales-audit.blade.php`](file:///C:/xampp/htdocs/EASYPOS/resources/views/reports/periodic-sales-audit.blade.php)): Styled with standard A4 paper portrait margins (`12mm 10mm 12mm 10mm`) and formal tax tables.

---

## License

EASYPOS is open-sourced software licensed under the [MIT license](LICENSE).
