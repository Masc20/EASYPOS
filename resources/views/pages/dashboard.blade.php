<x-layout.app-shell>
    <div class="mb-8">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">EASYPOS Dashboard</h1>
        <p class="mt-1 text-sm text-gray-500">Welcome to your modular Point of Sale and Inventory system</p>
    </div>

    <!-- Quick Navigation Cards -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 mb-8">
        <!-- POS Card -->
        <a href="{{ route('pos') }}" class="group relative rounded-xl border border-gray-200 bg-white p-6 shadow-xs transition hover:border-blue-500 hover:shadow-md">
            <div class="flex items-center justify-between">
                <span class="rounded-lg bg-blue-50 p-3 text-blue-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </span>
                <span class="text-xs font-semibold text-blue-600 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
            </div>
            <h2 class="mt-4 text-base font-semibold text-gray-900">Point of Sale</h2>
            <p class="mt-1 text-sm text-gray-500">Open cash terminal, scan items, and process customer checkout transactions.</p>
        </a>

        <!-- Inventory Card -->
        <a href="{{ route('inventory') }}" class="group relative rounded-xl border border-gray-200 bg-white p-6 shadow-xs transition hover:border-emerald-500 hover:shadow-md">
            <div class="flex items-center justify-between">
                <span class="rounded-lg bg-emerald-50 p-3 text-emerald-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </span>
                <span class="text-xs font-semibold text-emerald-600 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
            </div>
            <h2 class="mt-4 text-base font-semibold text-gray-900">Inventory</h2>
            <p class="mt-1 text-sm text-gray-500">Manage catalog products, check stock levels, and review alerts.</p>
        </a>

        <!-- Admin Backoffice Card -->
        <a href="{{ url('/admin') }}" class="group relative rounded-xl border border-gray-200 bg-white p-6 shadow-xs transition hover:border-amber-500 hover:shadow-md">
            <div class="flex items-center justify-between">
                <span class="rounded-lg bg-amber-50 p-3 text-amber-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </span>
                <span class="text-xs font-semibold text-amber-600 group-hover:translate-x-0.5 transition-transform">&rarr;</span>
            </div>
            <h2 class="mt-4 text-base font-semibold text-gray-900">Filament Admin</h2>
            <p class="mt-1 text-sm text-gray-500">Back-office panel for managers to configure stores, users, and roles.</p>
        </a>
    </div>

    <!-- Active User & Domain Status -->
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-xs">
        <h2 class="text-base font-semibold text-gray-900 mb-3">System & Modular Monolith Status</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div class="rounded-lg bg-gray-50 p-4 border border-gray-100">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Active Domain Slices</span>
                <p class="mt-1 text-lg font-bold text-gray-900">3 Domains</p>
                <p class="text-xs text-gray-500 mt-1">Identity, PointOfSale, Inventory</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-4 border border-gray-100">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Current User Session</span>
                @auth
                    <p class="mt-1 text-lg font-bold text-gray-900">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-blue-600 mt-1 font-medium">{{ auth()->user()->roles->pluck('name')->join(', ') ?: 'No role assigned' }}</p>
                @else
                    <p class="mt-1 text-lg font-bold text-gray-500">Guest</p>
                    <a href="{{ route('login') }}" class="text-xs text-blue-600 hover:underline mt-1 inline-block">Sign in to begin &rarr;</a>
                @endauth
            </div>
            <div class="rounded-lg bg-gray-50 p-4 border border-gray-100">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Installed Ecosystem</span>
                <p class="mt-1 text-lg font-bold text-gray-900">Laravel 12 + Livewire 3</p>
                <p class="text-xs text-gray-500 mt-1">Filament 4 • Spatie RBAC • Tailwind v4</p>
            </div>
        </div>
    </div>
</x-layout.app-shell>
