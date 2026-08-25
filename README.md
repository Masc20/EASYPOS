## Project Structure

**EASYPOS** follows a **Modular Monolith** architecture with **Vertical Slice** organization and a **Component-Driven** UI.

```text
.
├── app/
│   ├── Domains/                      # Business Logic (Bounded Contexts)
│   │   ├── PointOfSale/
│   │   │   ├── Actions/              # Use Cases (e.g., ProcessCheckout)
│   │   │   ├── Models/               # POS Entities (Sale, Cart, Receipt)
│   │   │   ├── Events/               # Domain Events (SaleCompleted)
│   │   │   └── Livewire/             # POS-Specific Smart Components
│   │   │       ├── Checkout/
│   │   │       │   ├── CheckoutCart.php
│   │   │       │   ├── CheckoutModal.php
│   │   │       │   └── ReceiptPreview.php
│   │   │       └── ProductSearch.php
│   │   │
│   │   └── Inventory/
│   │       ├── Actions/
│   │       ├── Models/               # IMS Entities (Product, Stock)
│   │       ├── Events/
│   │       └── Livewire/             # IMS-Specific Smart Components
│   │           ├── StockTable.php
│   │           ├── ProductForm.php
│   │           └── LowStockAlert.php
│   │
│   ├── Livewire/                     # SHARED UI COMPONENTS (Atomic Design)
│   │   ├── Layout/                   # App Shell, Sidebar, TopBar
│   │   │   ├── AppShell.php
│   │   │   ├── Sidebar.php
│   │   │   └── TopBar.php
│   │   │
│   │   ├── Ui/                       # Atoms (No Business Logic)
│   │   │   ├── Button.php
│   │   │   ├── Input.php
│   │   │   ├── Modal.php
│   │   │   ├── Table.php
│   │   │   ├── Dropdown.php
│   │   │   └── Badge.php
│   │   │
│   │   └── Forms/                    # Molecules (Reusable Form Fields)
│   │       ├── CurrencyInput.php
│   │       ├── ProductSelect.php
│   │       └── DatePicker.php
│   │
│   └── View/
│       └── Components/               # Blade Component Templates (Static)
│           └── Ui/
│               ├── button.blade.php
│               ├── modal.blade.php
│               └── input.blade.php
│
├── resources/
│   ├── views/
│   │   ├── livewire/                 # Livewire Component Views
│   │   │   ├── layout/
│   │   │   │   ├── app-shell.blade.php
│   │   │   │   └── sidebar.blade.php
│   │   │   ├── ui/                   # Templates for Atoms (Modal, Table)
│   │   │   │   ├── modal.blade.php
│   │   │   │   └── table.blade.php
│   │   │   ├── point-of-sale/        # Templates for POS Features
│   │   │   │   ├── checkout-cart.blade.php
│   │   │   │   └── checkout-modal.blade.php
│   │   │   └── inventory/            # Templates for IMS Features
│   │   │       ├── stock-table.blade.php
│   │   │       └── product-form.blade.php
│   │   │
│   │   ├── pages/                    # Entry Points (Thin Shells)
│   │   │   ├── pos.blade.php
│   │   │   ├── inventory.blade.php
│   │   │   └── dashboard.blade.php
│   │   │
│   │   └── components/               # Blade Component Templates
│   │       └── ui/
│   │           ├── button.blade.php
│   │           └── modal.blade.php
│   │
│   └── css/
│       └── app.css                   # Tailwind Directives
│
├── routes/
│   └── web.php                       # Routes map Pages to Layouts
│
├── tests/
│   ├── Feature/
│   └── Unit/
│
└── composer.json   
