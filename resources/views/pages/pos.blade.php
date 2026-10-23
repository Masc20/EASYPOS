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
            <!-- Order Progress Slide-Over Trigger Button -->
            <button type="button" 
                x-data 
                x-on:click="$dispatch('open-order-progress'); if (window.Livewire) { Livewire.dispatch('open-order-progress'); }"
                onclick="window.dispatchEvent(new CustomEvent('open-order-progress')); if (window.Livewire) { Livewire.dispatch('open-order-progress'); }"
                class="inline-flex items-center gap-1.5 rounded-md border border-brand-border bg-brand-card hover:bg-brand-muted px-3 py-1.5 text-xs font-semibold text-brand-text transition shadow-2xs cursor-pointer focus:outline-none focus-visible:ring-1 focus-visible:ring-brand-primary active:scale-95 select-none">
                <svg class="h-4 w-4 text-brand-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                <span>Order Progress</span>
            </button>

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

            <!-- Cashier Read-Only Kitchen Order Progress Slide-Over -->
            <livewire:pos.order-progress />
        </div>
    </div>

    <!-- Toast Alert Notification Container for POS Warnings & 86'd alerts -->
    <div x-data="{
        toasts: [],
        add(detail) {
            const data = Array.isArray(detail) ? detail[0] : detail;
            const toast = typeof data === 'object' && data !== null ? data : { message: String(data), type: 'warning' };
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message: toast.message || 'Notification', type: toast.type || 'warning' });
            setTimeout(() => this.remove(id), 3500);
        },
        remove(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        }
    }" x-on:pos-alert.window="add($event.detail)" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 max-w-sm pointer-events-none">
        <template x-for="t in toasts" :key="t.id">
            <div class="pointer-events-auto flex items-center gap-2.5 px-4 py-2.5 rounded-lg border shadow-lg text-xs font-medium transition-all"
                :class="t.type === 'error' ? 'bg-brand-error text-white border-brand-error' :
                       (t.type === 'warning' ? 'bg-amber-600 text-white border-amber-700' : 'bg-brand-card text-brand-text border-brand-border')">
                <svg class="h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <span x-text="t.message"></span>
            </div>
        </template>
    </div>
</x-layout.app-shell>
