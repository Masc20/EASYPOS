<x-layout.app-shell>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Point of Sale Terminal</h1>
            <p class="text-sm text-gray-500">Scan barcodes or search products to process sales transactions</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-medium text-emerald-700">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                Terminal Active
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-xs">
                <livewire:pos.product-search />
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-xs">
                <livewire:pos.checkout.checkout-cart />
            </div>
            <livewire:pos.checkout.checkout-modal />
        </div>
    </div>
</x-layout.app-shell>
