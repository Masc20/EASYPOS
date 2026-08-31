# EASYPOS

**EASYPOS** follows a **Modular Monolith** architecture with **Vertical Slice** organization and a **Component-Driven** UI.

```text
.
├── app/
│   ├── Domains/                              # Business logic
│   │   ├── PointOfSale/
│   │   │   ├── Actions/                      # Use cases, e.g. ProcessCheckout
│   │   │   │   └── ProcessCheckout.php
│   │   │   ├── Events/
│   │   │   │   └── SaleCompleted.php
│   │   │   ├── Models/                       # POS entities
│   │   │   │   ├── Cart.php
│   │   │   │   ├── Receipt.php
│   │   │   │   └── Sale.php
│   │   │   └── Livewire/                     # POS-specific smart components
│   │   │       ├── Checkout/
│   │   │       │   ├── CheckoutCart.php
│   │   │       │   ├── CheckoutModal.php
│   │   │       │   └── ReceiptPreview.php
│   │   │       └── ProductSearch.php
│   │   └── Inventory/
│   │       ├── Actions/
│   │       │   └── AdjustStock.php
│   │       ├── Events/
│   │       │   └── LowStockDetected.php
│   │       ├── Models/                       # Inventory entities
│   │       │   ├── Product.php
│   │       │   └── Stock.php
│   │       └── Livewire/                     # Inventory-specific smart components
│   │           ├── LowStockAlert.php
│   │           ├── ProductForm.php
│   │           └── StockTable.php
│   ├── Livewire/                             # Shared UI components
│   │   ├── Forms/                            # Reusable form fields
│   │   │   ├── CurrencyInput.php
│   │   │   ├── DatePicker.php
│   │   │   └── ProductSelect.php
│   │   ├── Layout/                           # Application shell and navigation
│   │   │   ├── AppShell.php
│   │   │   ├── Sidebar.php
│   │   │   └── TopBar.php
│   │   └── Ui/                               # Business-agnostic UI elements
│   │       ├── Badge.php
│   │       ├── Button.php
│   │       ├── Dropdown.php
│   │       ├── Input.php
│   │       ├── Modal.php
│   │       └── Table.php
│   └── View/Components/Ui/                   # Anonymous Blade component templates
│       ├── button.blade.php
│       ├── input.blade.php
│       └── modal.blade.php
├── resources/
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   └── app.js
│   └── views/
│       ├── components/
│       │   ├── layout/
│       │   │   └── app-shell.blade.php
│       │   └── ui/
│       │       ├── button.blade.php
│       │       └── modal.blade.php
│       ├── livewire/
│       │   ├── forms/
│       │   │   ├── currency-input.blade.php
│       │   │   ├── date-picker.blade.php
│       │   │   └── product-select.blade.php
│       │   ├── inventory/
│       │   │   ├── low-stock-alert.blade.php
│       │   │   ├── product-form.blade.php
│       │   │   └── stock-table.blade.php
│       │   ├── layout/
│       │   │   ├── app-shell.blade.php
│       │   │   ├── sidebar.blade.php
│       │   │   └── top-bar.blade.php
│       │   ├── point-of-sale/
│       │   │   ├── checkout/
│       │   │   │   ├── checkout-cart.blade.php
│       │   │   │   ├── checkout-modal.blade.php
│       │   │   │   └── receipt-preview.blade.php
│       │   │   └── product-search.blade.php
│       │   └── ui/
│       │       ├── badge.blade.php
│       │       ├── button.blade.php
│       │       ├── dropdown.blade.php
│       │       ├── input.blade.php
│       │       ├── modal.blade.php
│       │       └── table.blade.php
│       └── pages/                            # Thin route entry points
│           ├── dashboard.blade.php
│           ├── inventory.blade.php
│           └── pos.blade.php
├── routes/
│   └── web.php
├── tests/
│   ├── Feature/
│   └── Unit/
└── composer.json
```

## Conventions

- Add feature behavior to its domain slice before adding shared code.
- Keep routes and page views thin, compose pages from Livewire components.
- Put reusable, business-agnostic UI in `app/Livewire` and keep feature behavior in `app/Domains`.
- Add migrations and tests alongside each new capability.

## Running locally with XAMPP

### Prerequisites

- XAMPP with Apache, MySQL, and PHP 8.2 or later.
- [Composer](https://getcomposer.org/) available from the command line.
- Node.js 20 or later and npm.

### Install the project

Clone or copy the project into XAMPP's `htdocs` directory:

```powershell
cd C:\xampp\htdocs
git clone <repository-url> EASYPOS
cd EASYPOS
```

Install the PHP and frontend dependencies, then create the local environment file:

```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
```

### Configure MySQL

1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Open [phpMyAdmin](http://localhost/phpmyadmin) and create a database named `easypos`.
3. Update the database section of `.env`:

```dotenv
APP_NAME=EASYPOS
APP_URL=http://localhost/EASYPOS/public

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=easypos
DB_USERNAME=root
DB_PASSWORD=
```

The default XAMPP MySQL `root` account has no password. If yours does, set `DB_PASSWORD` to that password instead.

Run the database migrations and build frontend assets:

```powershell
php artisan migrate
php artisan storage:link
npm run build
```

Open the application at [http://localhost/EASYPOS/public](http://localhost/EASYPOS/public).

### During frontend development

Keep this command running in a separate terminal to rebuild CSS and JavaScript as files change:

```powershell
npm run dev
```

### Troubleshooting

After changing `.env`, configuration, routes, or cached views, clear Laravel's cached files:

```powershell
php artisan optimize:clear
```
