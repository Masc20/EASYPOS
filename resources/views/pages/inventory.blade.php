<x-layout.app-shell>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Inventory Management</h1>
            <p class="text-sm text-gray-500">Monitor stock levels, manage product records, and track stock alerts</p>
        </div>
    </div>

    <div class="mb-6">
        <livewire:inventory.low-stock-alert />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-xs">
                <livewire:inventory.stock-table />
            </div>
        </div>

        <div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-xs">
                <h2 class="mb-3 text-base font-semibold text-gray-900">Add / Update Product</h2>
                <livewire:inventory.product-form />
            </div>
        </div>
    </div>
</x-layout.app-shell>
