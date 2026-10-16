<x-layout.app-shell>
    <div class="mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-brand-text">Point of Sale Terminal</h1>
            <p class="text-xs text-brand-text-muted">
                {{ auth()->user()->branch?->name ?? 'Downtown Flagship' }} • Register Station • Staff: <span
                    class="font-semibold text-brand-text">{{ auth()->user()->name }}</span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span
                class="inline-flex items-center gap-1.5 rounded-sm bg-brand-success/10 border border-brand-success/30 px-2.5 py-1 text-xs font-semibold text-brand-success">
                <span class="h-2 w-2 rounded-xs bg-brand-success animate-pulse"></span>
                Terminal Active
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <!-- Main: Fast Search & Menu Catalog (2 cols) -->
        <div class="space-y-4 lg:col-span-2">
            <!-- Fast Search -->
            <livewire:pos.product-search />

            <!-- Dish / Menu Catalog Grid with Category Tabs -->
            <div class="rounded-lg border border-brand-border bg-brand-card p-4 shadow-2xs">
                <livewire:pos.meal-catalog />
            </div>
        </div>

        <!-- Sidebar: Active Order Cart & Modals (1 col) -->
        <div class="space-y-5">
            <div class="rounded-lg border border-brand-border bg-brand-card p-4 shadow-2xs">
                <livewire:pos.checkout.checkout-cart />
            </div>

            <!-- Checkout Payment Modal -->
            <livewire:pos.checkout.checkout-modal />

            <!-- Official Sales Receipt Modal -->
            <livewire:pos.checkout.receipt-preview />
        </div>
    </div>
</x-layout.app-shell>
