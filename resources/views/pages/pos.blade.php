<x-layout.app-shell>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-brand-text">Point of Sale Terminal</h1>
            <p class="text-sm text-brand-text-muted">Scan barcodes or search products to process sales transactions</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-success/10 border border-brand-success/30 px-3 py-1 text-xs font-semibold text-brand-success">
                <span class="h-2 w-2 rounded-full bg-brand-success animate-pulse"></span>
                Terminal Active
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-md border border-brand-border bg-brand-card p-5 shadow-2xs">
                <livewire:pos.product-search />
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-md border border-brand-border bg-brand-card p-5 shadow-2xs">
                <livewire:pos.checkout.checkout-cart />
            </div>
            <livewire:pos.checkout.checkout-modal />
        </div>
    </div>
</x-layout.app-shell>
