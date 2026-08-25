EASYPOS/
├── app/
│   ├── Domains/                      # Business Logic (Vertical Slices)
│   │   ├── PointOfSale/
│   │   │   ├── Actions/              # UseCases (e.g., ProcessCheckout)
│   │   │   ├── Models/               # POS Entities (Sale, Cart, Receipt)
│   │   │   ├── Events/               # Domain Events (SaleCompleted)
│   │   │   └── Livewire/             # POS-specific Livewire Components
│   │   │       ├── Checkout/
│   │   │       │   ├── CheckoutCart.php      # Logic: Add/Remove items
│   │   │       │   ├── CheckoutModal.php     # Logic: Payment processing
│   │   │       │   └── ReceiptPreview.php    # Logic: Print data prep
│   │   │       └── ProductSearch.php         # Logic: Barcode scanning
│   │   │
│   │   └── Inventory/
│   │       ├── Actions/
│   │       ├── Models/               # IMS Entities (Product, Stock, Supplier)
│   │       ├── Events/
│   │       └── Livewire/
│   │           ├── StockTable.php            # Logic: Sortable/Filterable table
│   │           ├── ProductForm.php           # Logic: Create/Edit validation
│   │           └── LowStockAlert.php         # Logic: Real-time notifications
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── DashboardController.php       # Only for initial page load
│   │   └── Middleware/
│   │
│   ├── Livewire/                     # 🔌 SHARED UI COMPONENTS (The "Lego Blocks")
│   │   ├── Layout/
│   │   │   ├── AppShell.php          # Main layout wrapper
│   │   │   ├── Sidebar.php           # Navigation logic
│   │   │   └── TopBar.php            # User profile, notifications
│   │   │
│   │   ├── Ui/                       # Reusable Atoms (No business logic)
│   │   │   ├── Button.php            # <x-ui.button />
│   │   │   ├── Input.php             # <x-ui.input />
│   │   │   ├── Modal.php             # <x-ui.modal /> (Handles open/close state)
│   │   │   ├── Table.php             # <x-ui.table /> (Sortable headers)
│   │   │   ├── Dropdown.php
│   │   │   └── Badge.php
│   │   │
│   │   └── Forms/                    # Reusable Form Fields
│   │       ├── CurrencyInput.php     # Auto-formats money
│   │       ├── ProductSelect.php     # Searchable dropdown for products
│   │       └── DatePicker.php
│   │
│   └── View/
│       └── Components/               # Blade Components (Static HTML/Tailwind)
│           └── Ui/                   # Maps to <x-ui.*> tags
│               ├── button.blade.php
│               ├── modal.blade.php
│               └── input.blade.php
│
├── resources/
│   ├── views/
│   │   ├── livewire/                 # 🔌 Livewire Component Views
│   │   │   ├── layout/
│   │   │   │   ├── app-shell.blade.php
│   │   │   │   └── sidebar.blade.php
│   │   │   ├── ui/
│   │   │   │   ├── modal.blade.php   # The HTML structure for Modal component
│   │   │   │   └── table.blade.php
│   │   │   ├── point-of-sale/
│   │   │   │   ├── checkout-cart.blade.php
│   │   │   │   └── checkout-modal.blade.php
│   │   │   └── inventory/
│   │   │       ├── stock-table.blade.php
│   │   │       └── product-form.blade.php
│   │   │
│   │   ├── pages/                    # Full Pages (Entry Points)
│   │   │   ├── pos.blade.php         # <livewire:point-of-sale.checkout-cart />
│   │   │   ├── inventory.blade.php   # <livewire:inventory.stock-table />
│   │   │   └── dashboard.blade.php
│   │   │
│   │   └── components/               # Blade Component Templates
│   │       └── ui/
│   │           ├── button.blade.php  # Tailwind classes live here
│   │           └── modal.blade.php
│   │
│   └── css/
│       └── app.css                   # Tailwind directives
│
├── routes/
│   └── web.php                       # Routes point to Pages, not Controllers
│
└── tests/
    ├── Feature/
    └── Unit/   
