<x-layout.app-shell>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-brand-text">Menu & Specials Management</h1>
            <p class="text-sm text-brand-text-muted">Manage chef-prepared meals, daily specials, categories, and live station availability</p>
        </div>
    </div>

    <div class="mb-6">
        <livewire:menu.daily-specials-board />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="rounded-md border border-brand-border bg-brand-card p-5 shadow-2xs">
                <livewire:menu.meal-catalog-table />
            </div>
        </div>

        <div>
            <div class="rounded-md border border-brand-border bg-brand-card p-5 shadow-2xs">
                <h2 class="mb-3 text-base font-bold text-brand-text">Add / Update Meal</h2>
                <livewire:menu.meal-form />
            </div>
        </div>
    </div>
</x-layout.app-shell>
