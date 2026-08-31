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
