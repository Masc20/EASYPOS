<?php

namespace App\Providers;

use App\Domains\Audit\Services\ActivityLogger;
use App\Domains\Identity\Events\UserLoggedIn;
use App\Domains\Identity\Events\UserLoggedOut;
use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Events\KitchenTicketClaimed;
use App\Domains\Kitchen\Events\KitchenTicketStatusUpdated;
use App\Domains\Menu\Events\MealAvailabilityToggled;
use App\Domains\PointOfSale\Events\OrderCreated;
use App\Domains\PointOfSale\Events\OrderVoided;
use App\Support\Constants\SystemConstants;
use App\Support\LivewireRequests;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Auth\Events\Failed as AuthFailed;
use Illuminate\Auth\Events\Login as AuthLogin;
use Illuminate\Auth\Events\Logout as AuthLogout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LivewireRequests::class);
        $this->app->alias(LivewireRequests::class, HandleRequests::class);
        $this->app->singleton(\App\Domains\PointOfSale\Services\SalesAggregationService::class);
    }

    public function boot(): void
    {
        FilamentTimezone::set(config('app.business_timezone'));
        Vite::usePreloadTagAttributes(false);

        Livewire::addPersistentMiddleware([
            \Spatie\Permission\Middleware\PermissionMiddleware::class,
            \Spatie\Permission\Middleware\RoleMiddleware::class,
            \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            \App\Http\Middleware\PreloadAuthenticatedUserPermissions::class,
        ]);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('pos', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('menu', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('reports', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Domain: PointOfSale
        Livewire::component('pos.checkout.checkout-cart', \App\Domains\PointOfSale\Livewire\Checkout\CheckoutCart::class);
        Livewire::component('pos.checkout.checkout-modal', \App\Domains\PointOfSale\Livewire\Checkout\CheckoutModal::class);
        Livewire::component('pos.checkout.receipt-preview', \App\Domains\PointOfSale\Livewire\Checkout\ReceiptPreview::class);
        Livewire::component('pos.checkout.manager-pin-modal', \App\Domains\PointOfSale\Livewire\Checkout\ManagerPinModal::class);
        Livewire::component('pos.product-search', \App\Domains\PointOfSale\Livewire\ProductSearch::class);
        Livewire::component('pos.meal-catalog', \App\Domains\PointOfSale\Livewire\MealCatalog::class);
        Livewire::component('pos.order-progress', \App\Domains\PointOfSale\Livewire\OrderProgress::class);

        // Domain: Menu
        Livewire::component('menu.meal-catalog-table', \App\Domains\Menu\Livewire\MealCatalogTable::class);
        Livewire::component('menu.meal-form', \App\Domains\Menu\Livewire\MealForm::class);
        Livewire::component('menu.create-meal-modal', \App\Domains\Menu\Livewire\CreateMealModal::class);
        Livewire::component('menu.edit-meal-modal', \App\Domains\Menu\Livewire\EditMealModal::class);
        Livewire::component('menu.daily-specials-board', \App\Domains\Menu\Livewire\DailySpecialsBoard::class);

        // Domain: Identity
        Livewire::component('identity.auth.login', \App\Domains\Identity\Livewire\Auth\Login::class);

        // Domain: Kitchen
        Livewire::component('kitchen.kitchen-display', \App\Domains\Kitchen\Livewire\KitchenDisplay::class);

        // Shared Smart Form Widgets
        Livewire::component('forms.product-select', \App\Livewire\Forms\ProductSelect::class);
        Livewire::component('forms.date-picker', \App\Livewire\Forms\DatePicker::class);
        Livewire::component('forms.currency-input', \App\Livewire\Forms\CurrencyInput::class);

        $this->registerActivityLogListeners();
    }

    private function registerActivityLogListeners(): void
    {
        Event::listen(UserLoggedIn::class, function (UserLoggedIn $event): void {
            ActivityLogger::success(
                category: 'attendance',
                event: 'clock_in',
                description: "{$event->user->name} clocked in via floor terminal.",
                actor: $event->user,
                branchId: $event->user->branch_id,
                subject: $event->user,
                metadata: ['auth_method' => 'pin'],
            );
        });

        Event::listen(UserLoggedOut::class, function (UserLoggedOut $event): void {
            ActivityLogger::success(
                category: 'attendance',
                event: 'clock_out',
                description: "{$event->user->name} clocked out / signed out.",
                actor: $event->user,
                branchId: $event->user->branch_id,
                subject: $event->user,
            );
        });

        Event::listen(AuthLogin::class, function (AuthLogin $event): void {
            if (! $event->user instanceof User) {
                return;
            }
            if (request()->hasSession() && request()->session()->get(SystemConstants::SESSION_AUTH_METHOD) === 'pin') {
                return;
            }
            // Skip duplicate if floor Login Livewire component is authenticating (it fires UserLoggedIn right after)
            if (request()->routeIs('login') || str_contains((string) request()->header('Referer', ''), '/login') && ! str_contains((string) request()->header('Referer', ''), '/admin/login')) {
                return;
            }
            ActivityLogger::success(
                category: 'attendance',
                event: 'clock_in',
                description: "{$event->user->name} signed in to back-office portal.",
                actor: $event->user,
                branchId: $event->user->branch_id,
                subject: $event->user,
                metadata: ['auth_method' => 'password'],
            );
        });

        Event::listen(AuthFailed::class, function (AuthFailed $event): void {
            $user = $event->user instanceof User ? $event->user : null;
            $identifier = (string) ($event->credentials['email'] ?? $event->credentials['emp_id'] ?? 'unknown');
            ActivityLogger::failed(
                category: 'auth',
                event: 'login_failed',
                description: "Failed login attempt for {$identifier}.",
                actor: $user,
                branchId: $user?->branch_id,
                metadata: ['identifier' => $identifier],
                actorName: $user?->name ?? $identifier,
                actorEmpId: $user?->emp_id,
            );
        });

        Event::listen(AuthLogout::class, function (AuthLogout $event): void {
            if (! $event->user instanceof User) {
                return;
            }
            // Floor & custom logout routes already emit UserLoggedOut; log back-office Filament logout here
            if (request()->is('admin/logout')) {
                ActivityLogger::success(
                    category: 'attendance',
                    event: 'clock_out',
                    description: "{$event->user->name} signed out of back-office portal.",
                    actor: $event->user,
                    branchId: $event->user->branch_id,
                    subject: $event->user,
                );
            }
        });

        Event::listen(OrderCreated::class, function (OrderCreated $event): void {
            $order = $event->order;
            ActivityLogger::success(
                category: 'pos',
                event: 'pos_checkout',
                description: "Created order {$order->order_number} (Total: {$order->total_amount}).",
                branchId: $order->branch_id,
                subject: $order,
                metadata: ['order_number' => $order->order_number, 'total_amount' => (float) $order->total_amount],
            );
        });

        Event::listen(OrderVoided::class, function (OrderVoided $event): void {
            ActivityLogger::success(
                category: 'pos',
                event: 'order_void',
                description: "Voided order {$event->order->order_number} (Approved by {$event->manager->name}; Reason: {$event->reason}).",
                actor: $event->manager,
                branchId: $event->order->branch_id,
                subject: $event->order,
                metadata: ['order_number' => $event->order->order_number, 'reason' => $event->reason],
            );
        });

        Event::listen(KitchenTicketClaimed::class, function (KitchenTicketClaimed $event): void {
            ActivityLogger::success(
                category: 'kitchen',
                event: 'kitchen_ticket_claimed',
                description: "{$event->cook->name} claimed kitchen ticket {$event->ticket->ticket_number}.",
                actor: $event->cook,
                branchId: $event->ticket->branch_id,
                subject: $event->ticket,
            );
        });

        Event::listen(KitchenTicketStatusUpdated::class, function (KitchenTicketStatusUpdated $event): void {
            ActivityLogger::success(
                category: 'kitchen',
                event: 'kitchen_ticket_status',
                description: "Updated ticket {$event->ticket->ticket_number} from {$event->previousStatus} to {$event->newStatus}.",
                branchId: $event->ticket->branch_id,
                subject: $event->ticket,
                metadata: ['from' => $event->previousStatus, 'to' => $event->newStatus],
            );
        });

        Event::listen(MealAvailabilityToggled::class, function (MealAvailabilityToggled $event): void {
            $state = ($event->effectiveAvailability ?? (bool) $event->meal->is_available) ? 'In Stock' : "86'd (Sold Out)";
            ActivityLogger::success(
                category: 'menu',
                event: 'menu_availability_toggled',
                description: "Marked dish '{$event->meal->name}' as {$state}.",
                branchId: $event->branchId ?? $event->meal->branch_id,
                subject: $event->meal,
                metadata: ['meal_id' => $event->meal->id, 'is_available' => $event->effectiveAvailability ?? (bool) $event->meal->is_available],
            );
        });
    }
}
