# EASYPOS &mdash; Centralized Multi-Branch POS & Inventory System

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-3.x-4E56A6?style=for-the-badge&logo=livewire&logoColor=white)](https://livewire.laravel.com)
[![Filament](https://img.shields.io/badge/Filament-4.x-F59E0B?style=for-the-badge&logo=filament&logoColor=white)](https://filamentphp.com)
[![TailwindCSS](https://img.shields.io/badge/TailwindCSS-v4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Spatie Permissions](https://img.shields.io/badge/Spatie-RBAC-blue?style=for-the-badge)](https://spatie.be/docs/laravel-permission)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)

**EASYPOS** is an enterprise-grade Point of Sale, Kitchen Display, and Inventory Management system built for multi-branch retail and food-service operations. It combines an autonomous **Floor Staff Touch Terminal** (powered by Employee IDs & 4-digit PIN authentication) with an executive **Filament Back-Office Portal** (powered by Email & Password credentials).

---

## Directory Structure

```text
EASYPOS/
├── app/
│   ├── Domains/                                  # Isolated business domain modules
│   │   ├── Branches/                             # Multi-Branch management
│   │   │   └── Models/
│   │   │       └── Branch.php
│   │   ├── Identity/                             # Authentication, RBAC, and staff profiles
│   │   │   ├── Actions/
│   │   │   │   └── CreateUser.php
│   │   │   ├── Events/
│   │   │   │   ├── UserLoggedIn.php
│   │   │   │   └── UserLoggedOut.php
│   │   │   ├── Livewire/
│   │   │   │   └── Auth/
│   │   │   │       └── Login.php                 # PIN Pad Floor Terminal component
│   │   │   └── Models/
│   │   │       └── User.php                      # RBAC, PIN verification, station routing
│   │   ├── Inventory/                            # Stock tracking, catalog & alerts
│   │   │   ├── Actions/
│   │   │   │   └── AdjustStock.php
│   │   │   ├── Events/
│   │   │   │   └── LowStockDetected.php
│   │   │   ├── Livewire/
│   │   │   │   ├── LowStockAlert.php
│   │   │   │   ├── ProductForm.php
│   │   │   │   └── StockTable.php
│   │   │   └── Models/
│   │   │       ├── Product.php
│   │   │       └── Stock.php
│   │   └── PointOfSale/                          # POS checkout & transaction processing
│   │       ├── Actions/
│   │       │   └── ProcessCheckout.php
│   │       ├── Events/
│   │       │   └── SaleCompleted.php
│   │       ├── Livewire/
│   │       │   ├── Checkout/
│   │       │   │   ├── CheckoutCart.php
│   │       │   │   ├── CheckoutModal.php
│   │       │   │   └── ReceiptPreview.php
│   │       │   └── ProductSearch.php
│   │       └── Models/
│   │           ├── Cart.php
│   │           ├── Receipt.php
│   │           └── Sale.php
│   ├── Livewire/                                 # Shared, domain-agnostic UI widgets
│   │   ├── Forms/                                # Generic inputs (Currency, Date, Select)
│   │   └── Ui/                                   # Base primitives (Badge, Button, Table, Modal)
│   ├── Models/
│   │   └── User.php                              # Framework compatibility proxy
│   └── Providers/
│       ├── AppServiceProvider.php
│       └── Filament/
│           └── AdminPanelProvider.php            # Multi-domain discovery for /admin
├── database/
│   ├── migrations/                               # Versioned schema migrations
│   └── seeders/
│       ├── DatabaseSeeder.php
│       └── RolesAndPermissionsSeeder.php         # Multi-branch roles & default users
├── resources/
│   ├── css/
│   │   └── app.css                               # Tailwind v4 theme variables & tokens
│   ├── js/
│   │   ├── app.js                                # Frontend entry point & EASYPOS namespace
│   │   ├── bootstrap.js
│   │   ├── components/
│   │   │   └── pin-pad.js                        # Zero-lag Alpine.js keypad controller
│   │   ├── modules/
│   │   │   ├── barcode-scanner.js                # Hardware & camera barcode listener
│   │   │   └── theme.js                          # Theme engine, cookie sync & event dispatch
│   │   └── utils/
│   │       └── formatters.js                     # Currency, date, and quantity helpers
│   └── views/
│       ├── components/
│       │   ├── layout/                           # Modular document and navigation shells
│       │   │   ├── app-shell.blade.php           # 26-line clean layout wrapper
│       │   │   ├── kiosk-shell.blade.php         # Minimalist terminal viewport shell
│       │   │   ├── navbar.blade.php              # Flexbox header with station integration
│       │   │   ├── station-badge.blade.php       # Live pulsing floor staff indicator
│       │   │   ├── nav-links.blade.php           # Permission-gated management links
│       │   │   ├── user-nav.blade.php            # User badge, Clock Out form, Theme toggle
│       │   │   └── theme-script.blade.php        # Synchronous anti-flash <head> engine
│       │   └── ui/
│       │       ├── button.blade.php
│       │       ├── modal.blade.php
│       │       └── theme-toggle.blade.php        # Sun / Moon toggle button
│       ├── errors/                               # Branded HTTP exception templates (400, 401, 403, 404, 419, 429, 500, 503, 4xx, 5xx, 3xx)
│       │   ├── layout.blade.php                  # Master terminal error shell with station routing
│       │   ├── minimal.blade.php                 # Framework fallback bridge
│       │   └── [400..503,4xx,5xx,3xx].blade.php  # Declarative status pages with diagnostics
│       ├── livewire/                             # Livewire Blade component views
│       └── pages/                                # Route destination views
│           ├── auth/login.blade.php              # Kiosk floor login
│           ├── dashboard.blade.php               # Multi-domain management dashboard
│           ├── inventory.blade.php               # Inventory station
│           ├── kitchen.blade.php                 # Kitchen display system (KDS)
│           └── pos.blade.php                     # POS terminal station
├── routes/
│   ├── auth.php                                  # Floor staff auth routes
│   └── web.php                                   # Station & domain routes
└── tests/
    └── Feature/
        ├── AuthTest.php                          # PIN & EmpID authentication tests
        ├── AuthorizationTest.php                 # Station access, isolation, & RBAC tests
        ├── ErrorPagesTest.php                    # HTTP error rendering, dark mode & station return tests
        └── FilamentPanelTest.php                 # Admin back-office security tests
```

---

## Role-Based Access Control (RBAC) & Station Routing

Every user is mapped to a strict permission set and assigned a dedicated station. Floor staff attempting to visit an unowned station will receive a `403 Forbidden` response and cannot see or click links redirecting outside their designated work area:

| Role | Auth Method | Assigned Station | Route | Key Permissions |
| :--- | :--- | :--- | :--- | :--- |
| **Cashier** | Emp ID + PIN | POS Register Terminal | `/pos` | `pos.access`, `pos.checkout` |
| **Chef** | Emp ID + PIN | Kitchen & Inventory Station | `/inventory` | `inventory.view`, `inventory.manage`, `kitchen.view` |
| **Cook** | Emp ID + PIN | Kitchen Display System (KDS) | `/kitchen` | `kitchen.view`, `kitchen.prepare` |
| **Branch Manager** | Email + Pass / PIN | Branch POS & Management | `/pos` & `/admin` | `admin.access`, `pos.access`, `inventory.view`, `branch.manage` |
| **System Owner** | Email + Password | Executive Back-Office | `/admin` | *All Permissions (Super Admin)* |

---

## Default Seeded Credentials

Run `php artisan db:seed --class=RolesAndPermissionsSeeder` to populate test accounts:

### 1. Floor Staff Terminal

> Sign in at `http://localhost:8000/login`

Test Users:

* **Cashier**: Employee ID `B01-CSH-001` &bull; PIN `1234`
* **Chef**: Employee ID `B01-CHF-001` &bull; PIN `2345`
* **Cook**: Employee ID `B01-COK-001` &bull; PIN `3456`
* **Manager**: Employee ID `B01-MGR-001` &bull; PIN `9999`

*(One-click quick-fill buttons are provided on the login screen for rapid local testing).*

### 2. Back-Office Management Portal

> Sign in at `http://localhost:8000/admin/login`

* **System Owner**: `owner@easypos.com` &bull; Password `password`
* **Branch Manager**: `mgr-b01@easypos.com` &bull; Password `password`

---

## Getting Started

### Prerequisites

* **PHP 8.2 or later** (with `pdo_mysql`, `mbstring`, `openssl`, `curl` extensions).
* **Composer 2.x**.
* **Node.js 20+** and `npm`.
* **MySQL 8.0+** or **MariaDB 10.4+** (XAMPP supported).

### Installation Steps

1. **Clone the repository**:

   ```bash
   git clone <repository-url> EASYPOS
   cd EASYPOS
   ```

2. **Install PHP and JavaScript dependencies**:

   ```bash
   composer install
   npm install
   ```

3. **Configure Environment**:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure Database**:
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

   ```bash
   php artisan migrate --seed
   ```

6. **Build Frontend Assets**:

   ```bash
   npm run build
   ```

7. **Start Development Servers**:
   In separate terminal tabs:

   ```bash
   php artisan serve
   ```

   ```bash
   npm run dev
   ```

Access the application:

* **Floor Staff Terminal**: [http://localhost:8000/login](http://localhost:8000/login)
* **Management Back-Office**: [http://localhost:8000/admin](http://localhost:8000/admin)

---

## Testing & Quality Assurance

The test suite validates authentication, role-based isolation, station routing, and theme persistence:

```bash
php vendor/bin/phpunit
```

### Test Coverage Highlights

* `tests/Feature/AuthTest.php`: Floor staff terminal rendering, PIN authentication for Cashier, Cook, Chef, and Manager, auto-submit keypad logic, lockout rate-limiting, and theme cookie rendering.
* `tests/Feature/AuthorizationTest.php`: Station access permissions, automatic redirection from `/` to assigned station, 403 denial on unauthorized routes, and floor staff navigation isolation.
* `tests/Feature/FilamentPanelTest.php`: Back-office authentication gates, ensuring PIN sessions cannot access administrative panels.

---

## Development Conventions

* **Domain Isolation**: When building new features, place models, actions, events, and business Livewire components inside `app/Domains/{DomainName}/`.
* **Blade Component Composition**: Keep `app-shell.blade.php` thin; compose layout features from `<x-layout.navbar />`, `<x-layout.station-badge />`, and `<x-layout.user-nav />`.
* **Zero Arbitrary Hexes**: Always use semantic design tokens (`bg-brand-card`, `text-brand-text`, `border-brand-border`) rather than hardcoded hex values.
* **Standard Radii**: Adhere strictly to `rounded-md` across inputs, buttons, badges, and card panels.
