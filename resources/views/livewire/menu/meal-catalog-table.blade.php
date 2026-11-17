<section x-data="{
    deleteMealId: null,
    deleteMealName: '',
    openDelete(id, name) {
        this.deleteMealId = id;
        this.deleteMealName = name;
        $dispatch('open-modal', 'delete-meal-modal');
    }
}">
    <!-- Filter Toolbar: Categories on Left, Search & Status on Right (Livewire Server-Side Filtering) -->
    <div class="mb-4 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <!-- Category Filter Tabs with Counts -->
        <div class="flex flex-wrap items-center gap-1.5">
            <button type="button" wire:click="filterByCategory(null)"
                class="rounded-md px-3 py-1.5 text-xs font-semibold transition cursor-pointer {{ is_null($selectedCategory) ? 'bg-brand-primary text-white shadow-2xs font-semibold' : 'bg-brand-bg text-brand-text-muted hover:text-brand-text border border-brand-border' }}">
                All ({{ $totalDishesCount }})
            </button>
            @foreach ($categories as $cat)
                <button type="button" wire:click="filterByCategory('{{ $cat->slug }}')"
                    class="rounded-md px-3 py-1.5 text-xs font-semibold transition cursor-pointer {{ $selectedCategory === $cat->slug ? 'bg-brand-primary text-white shadow-2xs font-semibold' : 'bg-brand-bg text-brand-text-muted hover:text-brand-text border border-brand-border' }}">
                    {{ $cat->name }} ({{ $cat->meals_count }})
                </button>
            @endforeach
        </div>

        <!-- Search & Status Filters -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Search Input with Livewire Debounced Filter -->
            <div class="relative w-full sm:w-56">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search dishes or codes..."
                    class="w-full rounded-md border border-brand-border bg-brand-bg px-3.5 py-1.5 pl-8 pr-7 text-xs text-brand-text placeholder:text-brand-text-muted/60 focus:border-brand-primary dark:focus:border-brand-secondary focus:bg-brand-card focus:outline-none focus:ring-1 focus:ring-brand-primary/20 transition">
                <svg class="pointer-events-none absolute left-2.5 top-2 h-3.5 w-3.5 text-brand-text-muted"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                @if ($search)
                    <button type="button" wire:click="$set('search', '')"
                        class="absolute right-2 top-2 text-brand-text-muted hover:text-brand-text cursor-pointer">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>

            <!-- Status Segments (Livewire Server Filter) -->
            <div class="inline-flex rounded-md border border-brand-border bg-brand-bg p-0.5 text-xs font-medium">
                <button type="button" wire:click="filterByStatus('all')"
                    class="rounded-sm px-2.5 py-1 transition cursor-pointer {{ $statusFilter === 'all' ? 'bg-brand-card text-brand-text shadow-2xs font-semibold' : 'text-brand-text-muted hover:text-brand-text' }}">
                    All
                </button>
                <button type="button" wire:click="filterByStatus('in_stock')"
                    class="rounded-sm px-2.5 py-1 transition cursor-pointer {{ $statusFilter === 'in_stock' ? 'bg-brand-card text-brand-success shadow-2xs font-semibold' : 'text-brand-text-muted hover:text-brand-text' }}">
                    In Stock
                </button>
                <button type="button" wire:click="filterByStatus('sold_out')"
                    class="rounded-sm px-2.5 py-1 transition cursor-pointer {{ $statusFilter === 'sold_out' ? 'bg-brand-card text-brand-error shadow-2xs font-semibold' : 'text-brand-text-muted hover:text-brand-text' }}">
                    86'd
                </button>
            </div>
        </div>
    </div>

    <!-- Table Container -->
    <div class="overflow-x-auto rounded-md border border-brand-border bg-brand-card">
        <table class="min-w-full divide-y divide-brand-border text-left text-sm">
            <thead class="bg-brand-bg/70 text-xs font-semibold uppercase tracking-wider text-brand-text-muted">
                <tr>
                    <th scope="col" class="px-4 py-3">Meal / Dish</th>
                    <th scope="col" class="px-3 py-3">Code</th>
                    <th scope="col" class="px-3 py-3">Category</th>
                    <th scope="col" class="px-3 py-3 text-center">Status</th>
                    <th scope="col" class="px-4 py-3 text-right">Price</th>
                    <th scope="col" class="px-3 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-border text-brand-text">
                @forelse ($meals as $meal)
                    @php
                        $isAvailable = $meal->isAvailableForBranch($branchId);
                        $trackStock = $meal->trackStockForBranch($branchId);
                        $stockQuantity = $meal->stockQuantityForBranch($branchId);
                        $canModifyRow =
                            !$isBranchScoped ||
                            ($meal->branch_id !== null && (int) $meal->branch_id === (int) $branchId);
                    @endphp
                    <tr wire:key="meal-row-{{ $meal->id }}"
                        class="transition hover:bg-brand-bg/40 {{ !$isAvailable ? 'opacity-65 bg-brand-bg/25' : '' }}">

                        <!-- Meal Name & Notes -->
                        <td class="px-4 py-3 max-w-sm">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="font-bold text-brand-text text-sm">{{ $meal->name }}</span>
                                @if (!$isBranchScoped)
                                    <span
                                        class="inline-flex items-center rounded-sm border border-brand-border bg-brand-bg px-1.5 py-0.5 text-[10px] font-semibold text-brand-text-muted">
                                        {{ $meal->branch_id ? $meal->branch?->name ?? 'Branch #' . $meal->branch_id : 'All Branches' }}
                                    </span>
                                @endif
                            </div>
                            @if ($meal->description)
                                <div class="text-xs text-brand-text-muted line-clamp-1">
                                    {{ $meal->description }}
                                </div>
                            @endif
                            @if ($trackStock && !is_null($stockQuantity))
                                <div
                                    class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-semibold text-brand-secondary">
                                    {{ $stockQuantity }} portions left today
                                </div>
                            @endif
                        </td>

                        <!-- Menu Code -->
                        <td class="px-3 py-3 font-mono text-xs">
                            @if ($meal->menu_code)
                                <span
                                    class="rounded-sm bg-brand-bg border border-brand-border px-1.5 py-0.5 font-semibold text-brand-text">
                                    {{ $meal->menu_code }}
                                </span>
                            @else
                                <span class="text-brand-text-muted">—</span>
                            @endif
                        </td>

                        <!-- Category -->
                        <td class="px-3 py-3 text-xs">
                            <span
                                class="rounded-sm bg-brand-bg/80 border border-brand-border/70 px-2 py-0.5 text-brand-text font-medium">
                                {{ $meal->category->name ?? 'Unassigned' }}
                            </span>
                        </td>

                        <!-- Status Toggle (86'd / In Stock) -->
                        <td class="px-3 py-3 text-center">
                            <button type="button" x-data
                                x-on:click.prevent="if (!navigator.onLine) { window.dispatchEvent(new CustomEvent('menu-alert', { detail: { type: 'error', message: 'Could not save availability. Please try again.' } })); return; } $wire.toggleAvailability({{ $meal->id }})"
                                wire:loading.attr="disabled" wire:target="toggleAvailability({{ $meal->id }})"
                                title="Click to toggle 86'd / In Stock availability"
                                class="inline-flex items-center gap-1.5 rounded-sm px-2.5 py-0.5 text-xs font-semibold transition cursor-pointer border shadow-2xs disabled:opacity-60 disabled:cursor-wait {{ $isAvailable ? 'bg-brand-success/15 text-brand-success border-brand-success/30 hover:bg-brand-success/25' : 'bg-brand-error/15 text-brand-error border-brand-error/30 hover:bg-brand-error/25' }}">
                                <span
                                    class="h-1.5 w-1.5 rounded-full {{ $isAvailable ? 'bg-brand-success' : 'bg-brand-error' }}"></span>
                                <span>{{ $isAvailable ? 'In Stock' : '86\'d (Sold Out)' }}</span>
                            </button>
                        </td>

                        <!-- Price -->
                        <td class="px-4 py-3 text-right font-mono">
                            <div class="text-sm font-bold text-brand-text">
                                {{ $currencySymbol }}{{ number_format($meal->price, 2) }}
                            </div>
                        </td>

                        <!-- Actions -->
                        <td class="px-3 py-3 text-right text-xs">
                            @if ($canModifyRow)
                                <div class="inline-flex items-center gap-1 justify-end">
                                    <button type="button" x-data="{ loading: false }"
                                        x-on:click="loading = true; Livewire.dispatch('edit-meal', { id: {{ $meal->id }} })"
                                        x-on:open-modal.window="loading = false" title="Edit dish"
                                        class="rounded-sm px-2 py-1 font-semibold text-brand-primary dark:text-brand-secondary hover:bg-brand-bg transition cursor-pointer">
                                        <span x-show="!loading">Edit</span>
                                        <span x-show="loading" x-cloak class="inline-flex items-center gap-1">
                                            <svg class="animate-spin h-3 w-3" viewBox="0 0 24 24" fill="none">
                                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                                    stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="M4 12a8 8 0 018-8v8H4z">
                                                </path>
                                            </svg>
                                        </span>
                                    </button>
                                    <button type="button"
                                        x-on:click="openDelete({{ $meal->id }}, '{{ addslashes($meal->name) }}')"
                                        title="Permanently remove branch dish"
                                        class="rounded-sm px-1.5 py-1 font-semibold text-brand-error/70 hover:text-brand-error hover:bg-brand-error/10 transition cursor-pointer">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            @else
                                <div class="inline-flex items-center gap-1.5 justify-end">
                                    <button type="button" wire:click="openPortionsModal({{ $meal->id }})"
                                        title="Adjust daily portions for your branch only"
                                        class="rounded-sm bg-brand-bg border border-brand-border/70 px-2 py-1 font-semibold text-brand-primary dark:text-brand-secondary hover:bg-brand-bg transition cursor-pointer">

                                        <span wire:loading.remove
                                            wire:target="openPortionsModal({{ $meal->id }})">Portions</span>

                                        <span wire:loading wire:target="openPortionsModal({{ $meal->id }})"
                                            class="inline-flex items-center gap-1">
                                            <svg class="animate-spin h-3 w-3" viewBox="0 0 24 24" fill="none">
                                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                                    stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="M4 12a8 8 0 018-8v8H4z"></path>
                                            </svg>
                                        </span>
                                    </button>
                                    <span
                                        class="inline-flex items-center rounded-sm px-2 py-0.5 text-[11px] font-medium text-brand-text-muted"
                                        title="Global menu items cannot be renamed, repriced, or deleted by branch staff. Use Portions or the status pill for your branch.">
                                        Global
                                    </span>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-brand-text-muted">
                            No dishes found in the menu catalog.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination Links -->
    @if ($meals->hasPages())
        <div class="mt-4 px-2">
            {{ $meals->links() }}
        </div>
    @endif

    <!-- Centralized Confirmation Modal for Permanently Removing Branch-Owned Dish -->
    <x-ui.confirm-modal name="delete-meal-modal" title="Permanently Remove Dish from Catalog" maxWidth="md">
        <p>
            Are you sure you want to permanently remove <strong class="text-brand-text font-bold"
                x-text="deleteMealName"></strong> from the catalog?
        </p>
        <p class="mt-1.5 text-xs text-brand-text-muted">
            To temporarily mark any active dish unavailable, use the <strong>In Stock / 86'd</strong> status toggle
            instead. Historical sales, kitchen tickets, and reporting records will remain preserved in the database.
        </p>

        <x-slot:action>
            <x-ui.button variant="danger"
                x-on:click="$wire.deleteMeal(deleteMealId); $dispatch('close-modal', 'delete-meal-modal')"
                wire:loading.attr="disabled" wire:target="deleteMeal">
                <span wire:loading.remove wire:target="deleteMeal">Remove Dish</span>
                <span wire:loading wire:target="deleteMeal" class="inline-flex items-center gap-1.5">
                    <svg class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    Removing...
                </span>
            </x-ui.button>
        </x-slot:action>
    </x-ui.confirm-modal>

    <!-- Branch-Scoped Portions Modal (allows Chefs to adjust portions for their branch on shared or branch meals) -->
    <x-ui.modal name="branch-portions-modal" title="Adjust Branch Portions" maxWidth="md">
        <form wire:submit="savePortions" class="space-y-4 text-left">
            <p class="text-xs text-brand-text-muted">
                Updating portions for <strong class="text-brand-text">{{ $portionsMealName }}</strong> applies to your
                branch only.
            </p>

            <div class="rounded-md border border-brand-border bg-brand-bg/50 p-3 space-y-3">
                <label class="flex items-center gap-2 text-xs font-semibold text-brand-text cursor-pointer">
                    <input type="checkbox" wire:model.live="portionsTrackStock"
                        class="h-4 w-4 rounded border-brand-border text-brand-primary focus:ring-brand-primary">
                    <span>Track daily portions for this branch</span>
                </label>

                @if ($portionsTrackStock)
                    <div>
                        <label for="portionsQuantity" class="block text-xs font-semibold text-brand-text mb-1">
                            Portions Left Today
                        </label>
                        <input id="portionsQuantity" type="number" min="0" step="1"
                            wire:model="portionsQuantity"
                            class="w-full rounded-md border border-brand-border bg-brand-card px-3 py-2 text-sm text-brand-text focus:border-brand-primary focus:outline-none focus:ring-1 focus:ring-brand-primary">
                        @error('portionsQuantity')
                            <p class="mt-1 text-xs font-medium text-brand-error">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" x-on:click="$dispatch('close-modal', 'branch-portions-modal')"
                    class="rounded-md border border-brand-border bg-brand-card px-3 py-1.5 text-xs font-semibold text-brand-text hover:bg-brand-bg transition cursor-pointer">
                    Cancel
                </button>
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="savePortions">
                    Save Portions
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</section>
