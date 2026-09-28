<x-layout.app-shell>
    <div class="mb-8">
        <h1 class="text-2xl font-bold tracking-tight text-brand-text">EASYPOS Dashboard</h1>
        <p class="mt-1 text-sm text-brand-text-muted">Welcome to your modular Point of Sale and Inventory system</p>
    </div>

    <!-- Quick Navigation Cards -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <!-- POS Card -->
        @can('pos.access')
            <a href="{{ route('pos') }}"
                class="group relative rounded-md border border-brand-border bg-brand-card p-6 shadow-2xs transition hover:border-brand-primary hover:shadow-md">
                <div class="flex items-center justify-between">
                    <span class="rounded-md bg-brand-primary/10 text-brand-primary dark:text-brand-secondary p-3">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </span>
                    <span
                        class="text-xs font-semibold text-brand-primary dark:text-brand-secondary group-hover:translate-x-1 transition-transform">&rarr;</span>
                </div>
                <h2 class="mt-4 text-base font-bold text-brand-text">Point of Sale</h2>
                <p class="mt-1 text-sm text-brand-text-muted">Open cash terminal, scan items, and process customer checkout
                    transactions.</p>
            </a>
        @endcan

        <!-- Inventory Card -->
        @can('inventory.view')
            <a href="{{ route('inventory') }}"
                class="group relative rounded-md border border-brand-border bg-brand-card p-6 shadow-2xs transition hover:border-brand-success hover:shadow-md">
                <div class="flex items-center justify-between">
                    <span class="rounded-md bg-brand-success/10 text-brand-success p-3">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </span>
                    <span
                        class="text-xs font-semibold text-brand-success group-hover:translate-x-1 transition-transform">&rarr;</span>
                </div>
                <h2 class="mt-4 text-base font-bold text-brand-text">Inventory</h2>
                <p class="mt-1 text-sm text-brand-text-muted">Manage catalog products, check stock levels, and review
                    alerts.</p>
            </a>
        @endcan

        <!-- Kitchen Display Card -->
        @can('kitchen.view')
            <a href="{{ route('kitchen') }}"
                class="group relative rounded-md border border-brand-border bg-brand-card p-6 shadow-2xs transition hover:border-brand-accent hover:shadow-md">
                <div class="flex items-center justify-between">
                    <span class="rounded-md bg-brand-accent/20 text-brand-text p-3">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" />
                        </svg>
                    </span>
                    <span
                        class="text-xs font-semibold text-brand-secondary group-hover:translate-x-1 transition-transform">&rarr;</span>
                </div>
                <h2 class="mt-4 text-base font-bold text-brand-text">Kitchen Display</h2>
                <p class="mt-1 text-sm text-brand-text-muted">Monitor active food tickets, order prep status, and
                    fulfillment queues.</p>
            </a>
        @endcan

        <!-- Admin Backoffice Card -->
        @can('admin.access')
            <a href="{{ url('/admin') }}"
                class="group relative rounded-md border border-brand-border bg-brand-card p-6 shadow-2xs transition hover:border-brand-secondary hover:shadow-md">
                <div class="flex items-center justify-between">
                    <span class="rounded-md bg-brand-secondary/15 text-brand-secondary p-3">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </span>
                    <span
                        class="text-xs font-semibold text-brand-secondary group-hover:translate-x-1 transition-transform">&rarr;</span>
                </div>
                <h2 class="mt-4 text-base font-bold text-brand-text">Filament Admin</h2>
                <p class="mt-1 text-sm text-brand-text-muted">Back-office panel for managers to configure stores, users, and
                    roles.</p>
            </a>
        @endcan
    </div>

    <!-- Active User & Domain Status -->
    <div class="rounded-md border border-brand-border bg-brand-card p-6 shadow-2xs">
        <h2 class="text-base font-bold text-brand-text mb-3">System & Modular Monolith Status</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div class="rounded-md bg-brand-bg/70 p-4 border border-brand-border/70">
                <span class="text-xs font-semibold text-brand-text-muted uppercase tracking-wider">Active Domain
                    Slices</span>
                <p class="mt-1 text-lg font-black text-brand-text">3 Domains</p>
                <p class="text-xs text-brand-text-muted mt-1">Identity, PointOfSale, Inventory</p>
            </div>
            <div class="rounded-md bg-brand-bg/70 p-4 border border-brand-border/70">
                <span class="text-xs font-semibold text-brand-text-muted uppercase tracking-wider">Current User
                    Session</span>
                @auth
                    <p class="mt-1 text-lg font-black text-brand-text">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-brand-primary dark:text-brand-secondary mt-1 font-semibold">
                        {{ auth()->user()->roles->pluck('name')->join(', ') ?: 'No role assigned' }}</p>
                @else
                    <p class="mt-1 text-lg font-black text-brand-text-muted">Guest</p>
                    <a href="{{ route('login') }}"
                        class="text-xs text-brand-primary dark:text-brand-secondary font-semibold hover:underline mt-1 inline-block">Sign
                        in to begin &rarr;</a>
                @endauth
            </div>
            <div class="rounded-md bg-brand-bg/70 p-4 border border-brand-border/70">
                <span class="text-xs font-semibold text-brand-text-muted uppercase tracking-wider">Installed
                    Ecosystem</span>
                <p class="mt-1 text-lg font-black text-brand-text">Laravel 12 + Livewire 3</p>
                <p class="text-xs text-brand-text-muted mt-1">Filament 4 • Spatie RBAC • Tailwind v4</p>
            </div>
        </div>
    </div>
</x-layout.app-shell>
