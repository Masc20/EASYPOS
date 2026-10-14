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

        // Domain: Menu
        Livewire::component('menu.meal-catalog-table', \App\Domains\Menu\Livewire\MealCatalogTable::class);
        Livewire::component('menu.meal-form', \App\Domains\Menu\Livewire\MealForm::class);
        Livewire::component('menu.create-meal-modal', \App\Domains\Menu\Livewire\CreateMealModal::class);
        Livewire::component('menu.edit-meal-modal', \App\Domains\Menu\Livewire\EditMealModal::class);
        Livewire::component('menu.daily-specials-board', \App\Domains\Menu\Livewire\DailySpecialsBoard::class);

        // Domain: Identity
        Livewire::component('identity.auth.login', \App\Domains\Identity\Livewire\Auth\Login::class);

        // Anonymous Blade components (just .blade.php files)
        Blade::anonymousComponentPath(app_path('View/Components/Ui'), 'ui');
    }
}