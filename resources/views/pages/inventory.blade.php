<x-layout.app-shell>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-brand-text">Inventory Management</h1>
            <p class="text-sm text-brand-text-muted">Monitor stock levels, manage product records, and track stock alerts</p>
        </div>
    </div>

    <div class="mb-6">
        <livewire:inventory.low-stock-alert />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="rounded-md border border-brand-border bg-brand-card p-5 shadow-2xs">
                <livewire:inventory.stock-table />
            </div>
        </div>

        <div>
            <div class="rounded-md border border-brand-border bg-brand-card p-5 shadow-2xs">
                <h2 class="mb-3 text-base font-bold text-brand-text">Add / Update Product</h2>
                <livewire:inventory.product-form />
            </div>
        </div>
    </div>
</x-layout.app-shell>
