<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Domain: PointOfSale
        Livewire::component('pos.checkout.checkout-cart', \App\Domains\PointOfSale\Livewire\Checkout\CheckoutCart::class);
        Livewire::component('pos.checkout.checkout-modal', \App\Domains\PointOfSale\Livewire\Checkout\CheckoutModal::class);
        Livewire::component('pos.checkout.receipt-preview', \App\Domains\PointOfSale\Livewire\Checkout\ReceiptPreview::class);
        Livewire::component('pos.product-search', \App\Domains\PointOfSale\Livewire\ProductSearch::class);

        // Domain: Inventory
        Livewire::component('inventory.stock-table', \App\Domains\Inventory\Livewire\StockTable::class);
        Livewire::component('inventory.product-form', \App\Domains\Inventory\Livewire\ProductForm::class);
        Livewire::component('inventory.low-stock-alert', \App\Domains\Inventory\Livewire\LowStockAlert::class);

        // Anonymous Blade components (just .blade.php files)
        Blade::anonymousComponentPath(app_path('View/Components/Ui'), 'ui');
    }
}