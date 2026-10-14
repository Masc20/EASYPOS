<x-layout.app-shell>
    <!-- Top Header: Single Clean Title & Add Dish Button (Instant 0ms Open) -->
    <div class="mb-5 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-bold tracking-tight text-brand-text">Meal Catalog</h1>
            <x-ui.badge variant="primary">
                Chef Station
            </x-ui.badge>
        </div>
        <x-ui.button
            x-data
            x-on:click="$dispatch('open-modal', 'create-meal-modal')"
            variant="primary">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add New Dish
        </x-ui.button>
    </div>

    <!-- Daily Specials Notice -->
    <div class="mb-5">
        <livewire:menu.daily-specials-board />
    </div>

    <!-- Main Full-Width Catalog in Centralized Card -->
    <x-ui.card padding="p-5">
        <livewire:menu.meal-catalog-table />
    </x-ui.card>

    <!-- Dedicated Create Meal Modal (Always Pristine & Blank, 0ms Open) -->
    <livewire:menu.create-meal-modal />

    <!-- Dedicated Edit Meal Modal (Hydrates Specific Meal on Demand) -->
    <livewire:menu.edit-meal-modal />
</x-layout.app-shell>
