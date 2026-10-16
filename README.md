<div align="center">

# EASYPOS &mdash; Centralized Multi-Branch POS System

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-3.x-4E56A6?style=for-the-badge&logo=livewire&logoColor=white)](https://livewire.laravel.com)
[![Filament](https://img.shields.io/badge/Filament-4.x-F59E0B?style=for-the-badge&logo=filament&logoColor=white)](https://filamentphp.com)
[![TailwindCSS](https://img.shields.io/badge/TailwindCSS-v4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Spatie Permissions](https://img.shields.io/badge/Spatie-RBAC-blue?style=for-the-badge)](https://spatie.be/docs/laravel-permission)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)

</div>

**EASYPOS** is an enterprise-grade Point of Sale, Kitchen Display, and Menu Management system created for multi-branch retail and restaurant operations. It features a **Modular Monolith** architecture with Domain-Driven Design (DDD), combining an autonomous **Floor Staff Touch Terminal** (Employee IDs + 4-digit PIN authentication) with an executive **Filament Back-Office Portal** (Email + Password authentication).

---

## Architecture & Directory Structure

EASYPOS organizes business capabilities into isolated domains under `app/Domains/`, uses reusable smart form widgets under `app/Livewire/Forms/`, and keeps pure presentation primitives under `resources/views/components/ui/`.

```text
EASYPOS/
├── app/
│   ├── Domains/                                  # Isolated business domain modules (DDD)
│   │   ├── Branches/                             # Multi-Branch management
│   │   │   └── Models/
│   │   │       └── Branch.php                    # Branch entity & store metadata
│   │   ├── Identity/                             # Authentication, RBAC, and staff profiles
│   │   │   ├── Actions/
│   │   │   │   └── CreateUser.php                # Staff onboarding action
│   │   │   ├── Events/
│   │   │   │   ├── UserLoggedIn.php
│   │   │   │   └── UserLoggedOut.php
│   │   │   ├── Livewire/
│   │   │   │   └── Auth/
│   │   │   │       └── Login.php                 # PIN Pad Floor Terminal component
│   │   │   └── Models/
│   │   ├── Kitchen/                              # Kitchen Display System (KDS) & cook queue
│   │   │   ├── Actions/
│   │   │   │   ├── ClaimKitchenTicketAction.php  # Assign cook & transition to in_prep
│   │   │   │   ├── RecallKitchenTicketAction.php # Undo bump and return to active queue
│   │   │   │   ├── ToggleKitchenItemStatusAction.php # Dish checkbox (cooking <-> done)
│   │   │   │   ├── ToggleKitchenTicketPriorityAction.php # Rush order priority toggle
│   │   │   │   └── UpdateKitchenTicketStatusAction.php # Status lifecycle & order sync
│   │   │   ├── Events/
│   │   │   │   ├── KitchenTicketClaimed.php
│   │   │   │   ├── KitchenTicketItemStatusUpdated.php
│   │   │   │   └── KitchenTicketStatusUpdated.php
│   │   │   ├── Livewire/
│   │   │   │   └── KitchenDisplay.php            # 3-column live prep queue with audio chime
│   │   │   └── Models/
│   │   │       ├── KitchenTicket.php             # Sequential cook ticket entity (K-001)
│   │   │       └── KitchenTicketItem.php         # Dish line-item prep status & instructions
│   │   ├── Menu/                                 # Dish catalog, pricing & availability
│   │   │   ├── Actions/
│   │   │   │   ├── CreateMealAction.php
│   │   │   │   ├── DeleteMealAction.php
│   │   │   │   ├── ToggleMealAvailabilityAction.php
│   │   │   │   └── UpdateMealAction.php
│   │   │   ├── Events/
│   │   │   │   ├── MealAvailabilityToggled.php
│   │   │   │   ├── MealCreated.php
│   │   │   │   ├── MealDeleted.php
│   │   │   │   └── MealUpdated.php
│   │   │   ├── Livewire/
│   │   │   │   ├── CreateMealModal.php
│   │   │   │   ├── DailySpecialsBoard.php
│   │   │   │   ├── EditMealModal.php
│   │   │   │   ├── MealCatalogTable.php
│   │   │   │   └── MealForm.php
│   │   │   └── Models/
│   │   │       ├── Meal.php                      # Dish model with portion costs & stock
│   │   │       └── MealCategory.php              # Dish categories (Appetizers, Mains, etc.)
│   │   └── PointOfSale/                          # POS checkout, cart & transaction processing
│   │       ├── Actions/
│   │       │   ├── CreateOrderAction.php         # Transactional order numbering & portion tracking
│   │       │   └── ProcessPaymentAction.php      # Settlement & automatic kitchen ticket dispatch
│   │       ├── Events/
│   │       │   ├── KitchenTicketDispatched.php
│   │       │   ├── OrderCreated.php
│   │       │   └── PaymentProcessed.php
│   │       ├── Livewire/
│   │       │   ├── Checkout/
│   │       │   │   ├── CheckoutCart.php          # 0ms Alpine-driven live register cart
│   │       │   │   ├── CheckoutModal.php         # Tender calculation & payment processing
│   │       │   │   └── ReceiptPreview.php        # 80mm BIR-compliant thermal print view
│   │       │   ├── MealCatalog.php               # Category tabs & responsive meal grid
│   │       │   └── ProductSearch.php             # Real-time search & barcode scanner integration
│   │       └── Models/
│   │           ├── Order.php                     # Orders with Philippine BIR tax accessors
│   │           ├── OrderItem.php                 # Price snapshot, portions & special instructions
│   │           └── Payment.php                   # Cash, card, and digital QR transaction audit
│   ├── Livewire/                                 # Shared, cross-domain smart widgets
│   │   └── Forms/
│   │       ├── CurrencyInput.php                 # Smart currency input with quick increments
│   │       ├── DatePicker.php                    # Smart date picker with quick presets
│   │       └── ProductSelect.php                 # Live meal search & selection dropdown
│   ├── Models/
│   │   └── User.php                              # Framework compatibility proxy
│   └── Providers/
│       ├── AppServiceProvider.php                # Component aliases & domain bindings
│       └── Filament/
│           └── AdminPanelProvider.php            # Multi-domain discovery for /admin
├── database/
│   ├── migrations/                               # Versioned schema migrations
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── MenuSeeder.php                        # Pre-configured categories & dishes
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
│       │   │   ├── app-shell.blade.php           # Master layout wrapper with theme engine
│       │   │   ├── kiosk-shell.blade.php         # Minimalist terminal viewport shell
│       │   │   ├── nav-links.blade.php           # Permission-gated management links
│       │   │   ├── navbar.blade.php              # Flexbox header with station integration
│       │   │   ├── station-badge.blade.php       # Live floor staff indicator
│       │   │   ├── theme-script.blade.php        # Synchronous anti-flash <head> engine
│       │   │   └── user-nav.blade.php            # User badge, Clock Out form, Theme toggle
│       │   └── ui/                               # Pure Blade presentational primitives (<x-ui.*>)
│       │       ├── badge.blade.php               # High-contrast status badges
│       │       ├── button.blade.php              # Sharpened buttons with variants & sizes
│       │       ├── card.blade.php                # High-density card container
│       │       ├── confirm-modal.blade.php       # Reusable confirmation dialog
│       │       ├── input.blade.php               # Form input with labels & error states
│       │       ├── modal.blade.php               # 0ms Alpine modal with backdrop blur
│       │       └── theme-toggle.blade.php        # Light / Dark mode toggle
│       ├── errors/                               # Branded HTTP exception templates (400-503)
│       ├── livewire/                             # Livewire Blade component views
│       └── pages/                                # Route destination views
│           ├── auth/login.blade.php              # Kiosk floor login
│           ├── dashboard.blade.php               # Multi-domain management dashboard
│           ├── kitchen.blade.php                 # Kitchen display system (KDS)
│           ├── menu.blade.php                    # Chef's menu catalog & dish management
│           └── pos.blade.php                     # POS terminal station
├── routes/
│   ├── auth.php                                  # Floor staff auth routes
│   └── web.php                                   # Station & domain routes
└── tests/
    └── Feature/
        ├── AuthTest.php                          # PIN & EmpID authentication tests
        ├── AuthorizationTest.php                 # Station access, isolation, & RBAC tests
        ├── ErrorPagesTest.php                    # HTTP error rendering & dark mode tests
        ├── FilamentPanelTest.php                 # Admin back-office security tests
        ├── KitchenTest.php                       # Kitchen Display, cook prep queue & bump tests
        ├── MenuDatabaseTest.php                  # Dish creation, toggling & category tests
        └── PointOfSaleTest.php                   # End-to-end POS, cart, tax & payment tests
```

---

1. **Tier 1 &mdash; Pure UI Primitives (`resources/views/components/ui/`)**:
   Zero-overhead native Blade components (`<x-ui.button>`, `<x-ui.modal>`, `<x-ui.input>`, `<x-ui.badge>`). Styled with sharpened `rounded-md` borders, semantic Tailwind color tokens, and 0ms local Alpine.js transitions. They never invoke server roundtrips for open/close actions or styling states.
2. **Tier 2 &mdash; Reusable Smart Widgets (`app/Livewire/Forms/`)**:
   Cross-domain Livewire widgets (`ProductSelect`, `DatePicker`, `CurrencyInput`) providing reusable stateful functionality across multiple stations.
3. **Tier 3 &mdash; Domain Smart Components (`app/Domains/{Domain}/Livewire/`)**:
   Full-featured domain components that directly query models, dispatch domain events, track portions, and execute transactional actions.

---

## Key Domain Capabilities

### 1. Point of Sale (POS)

* **0ms Client Responsiveness**: Adding meals to the cart, modifying quantities, switching between Dine-In and Takeout, applying discounts, and switching payment tabs occur instantly on the client via Alpine.js with zero server wait.
* **Component Split**: Distinct separation between [`ProductSearch.php`](file:///C:/xampp/htdocs/EASYPOS/app/Domains/PointOfSale/Livewire/ProductSearch.php) (dedicated strictly to barcode/keyword queries) and [`MealCatalog.php`](file:///C:/xampp/htdocs/EASYPOS/app/Domains/PointOfSale/Livewire/MealCatalog.php) (category tabs and dish cards).
* **Payment Settlement**: Cash, Credit/Debit Card, and Digital QR support with automated change computation and reference tracking.
* **Philippine BIR Tax Compliance**: Built-in computed accessors on [`Order.php`](file:///C:/xampp/htdocs/EASYPOS/app/Domains/PointOfSale/Models/Order.php) for:
  - **VATable Sales (12%)**: Sales amount net of 12% VAT.
  - **12% VAT Amount**: Explicit tax line.
  - **VAT-Exempt Sales**: Non-taxable transactions (Senior Citizen / PWD).
  - **Zero-Rated Sales**: 0% rate sales.
* **80mm Thermal Receipt Printing**: Clean `@media print` isolation in [`ReceiptPreview.blade.php`](file:///C:/xampp/htdocs/EASYPOS/resources/views/livewire/point-of-sale/checkout/receipt-preview.blade.php) that hides background navigation and headers, restricting physical printout strictly to the 80mm receipt roll. Includes Store TIN, MIN, Cashier ID, and Senior/PWD signature lines.
* **Cook Station Integration**: Completing a sale automatically generates a sequential kitchen ticket (`K-001`, `K-002`) and kitchen ticket items.

### 2. Menu & Dish Management

* **Catalog Management**: Paginated dish table with category filtering, real-time search, selling prices, portion costs, and gross margin calculations.
* **Availability Toggling**: Instant one-click toggling of dish availability (`is_available`), automatically hiding or disabling 86'd items on the POS floor in real time.
* **Stock & Portion Tracking**: Tracks remaining portion countdowns and warns cashiers when dishes run low.

### 3. Kitchen Display System (KDS)

* **Sequential Cook Tickets**: Tracks tickets from `new` &rarr; `in_prep` &rarr; `ready` &rarr; `served`.
* **Line-Item Instructions**: Captures chef notes and special instructions (e.g. `NO ONIONS`, `EXTRA SPICY`).

---

## Role-Based Access Control (RBAC) & Station Routing

Every floor user is assigned an Employee ID, a 4-digit PIN, and a dedicated operational station. Floor staff attempting to visit an unauthorized station receive a `403 Forbidden` response and are isolated to their work area:

| Role | Auth Method | Assigned Station | Route | Key Permissions |
| :--- | :--- | :--- | :--- | :--- |
| **Cashier** | Emp ID + PIN | POS Register Terminal | `/pos` | `pos.access`, `pos.checkout` |
| **Chef** | Emp ID + PIN | Chef's Menu & Kitchen Station | `/menu` | `menu.view`, `menu.manage`, `kitchen.view` |
| **Cook** | Emp ID + PIN | Kitchen Display System (KDS) | `/kitchen` | `kitchen.view`, `kitchen.prepare` |
| **Branch Manager** | Email + Pass / PIN | Branch POS & Management | `/pos` & `/admin` | `admin.access`, `pos.access`, `menu.view`, `branch.manage` |
| **System Owner** | Email + Password | Executive Back-Office | `/admin` | *All Permissions (Super Admin)* |

---

## Default Seeded Credentials

Run `php artisan db:seed` to populate test accounts:

### 1. Floor Staff Terminal

> Sign in at [http://localhost:8000/login](http://localhost:8000/login)

* **Cashier**: Employee ID `B01-CSH-001` &bull; PIN `1234`
* **Chef**: Employee ID `B01-CHF-001` &bull; PIN `2345`
* **Cook**: Employee ID `B01-COK-001` &bull; PIN `3456`
* **Manager**: Employee ID `B01-MGR-001` &bull; PIN `9999`

*(Quick-fill buttons are provided on the login screen for rapid local testing).*

### 2. Back-Office Management Portal

> Sign in at [http://localhost:8000/admin/login](http://localhost:8000/admin/login)

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

   ```powershell
   cd C:\xampp\htdocs
   git clone <repository-url> EASYPOS
   cd EASYPOS
   ```

2. **Install PHP and JavaScript dependencies**:

   ```powershell
   composer install
   npm install
   ```

3. **Configure Environment**:

   ```powershell
   Copy-Item .env.example .env
   php artisan key:generate
   ```

4. **Configure Database**:
   Update `.env` with your database settings:

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

6. **Build Frontend Assets**:

   ```powershell
   npm run build
   ```

7. **Start Development Server**:

   ```powershell
   php artisan serve
   ```

   In a separate terminal for active frontend hot-reloading:

   ```powershell
   npm run dev
   ```

* **Floor Staff Terminal**: [http://localhost:8000/login](http://localhost:8000/login)
* **Back-Office Admin**: [http://localhost:8000/admin](http://localhost:8000/admin)

---

## Testing & Quality Assurance

The automated test suite covers floor authentication, station routing isolation, menu operations, and complete POS checkout workflows:

```powershell
php vendor/bin/phpunit
```

### Test Suite Highlights

* **`tests/Feature/PointOfSaleTest.php`** (10 tests, 30 assertions):
  - POS terminal rendering and active status verification
  - Meal catalog category filtering and dish cards
  - Real-time catalog search event propagation
  - Exact match barcode direct-add to cart
  - Transactional order creation and portion decrementing
  - Cash payment validation and change computation
  - Digital / card reference requirement validation
  - Sequential kitchen ticket (`K-001`) and item generation
  - Philippine BIR tax breakdown accessors (`vatable_sales`, `vat_amount`, `vat_exempt_sales`)
* **`tests/Feature/KitchenTest.php`** (10 tests, 35 assertions):
  - KDS board access authorization (Cook/Chef allowed, Cashier 403 denied)
  - Incoming POS ticket rendering with table numbers and special instructions
  - Cook ticket claiming and status transition to `in_prep`
  - Individual item-level checkoffs (`pending` <-> `done`) with auto-advance to `ready`
  - Marking tickets plated and ready with runner pickup notification
  - Bumping served tickets and synchronizing linked order completion
  - Recalling accidentally bumped tickets back to the active queue
  - Rush priority toggle (`normal` <-> `rush`)
  - Real-time dining type filtering (Dine In vs Takeout)
* **`tests/Feature/AuthTest.php`**: Floor staff terminal rendering, PIN authentication for Cashier, Cook, Chef, and Manager, auto-submit keypad logic, lockout rate-limiting, and theme cookie persistence.
* **`tests/Feature/AuthorizationTest.php`**: Station access permissions, automatic redirection from `/` to assigned station, 403 denial on unauthorized routes, and floor staff navigation isolation.
* **`tests/Feature/MenuDatabaseTest.php`**: Meal CRUD actions, soft deletes, availability toggling, and category relations.
* **`tests/Feature/FilamentPanelTest.php`**: Back-office authentication gates, ensuring PIN sessions cannot access administrative panels.

---

## UI Design Tokens & Styling Rules

* **Design Primitive**: High-density POS styling with sharpened `rounded-md` borders across inputs, buttons, badges, and card panels. Strictly avoid bubbly `rounded-2xl` and `rounded-full` pills on primary actions.
* **Semantic Tokens**: Use theme color variables (`bg-brand-card`, `text-brand-text`, `border-brand-border`, `bg-brand-primary`) rather than hardcoded hex codes to ensure complete light and dark mode compatibility.
* **Instant Client Transitions**: Non-persistence UI actions (modals, category tabs, cart counters, tenders) must execute on the client in 0ms using Alpine.js.
