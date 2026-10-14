<section x-data="{
    activeCategory: 'all',
    activeStatus: 'all',
    searchQuery: '',
    totalVisible: {{ count($meals) }},
    deleteMealId: null,
    deleteMealName: '',
    matches(categorySlug, isAvailable, name, code) {
        if (this.activeCategory !== 'all' && this.activeCategory !== categorySlug) {
            return false;
        }
        if (this.activeStatus === 'in_stock' && !isAvailable) {
            return false;
        }
        if (this.activeStatus === 'sold_out' && isAvailable) {
            return false;
        }
        if (this.searchQuery.trim() !== '') {
            const q = this.searchQuery.toLowerCase().trim();
            const n = (name || '').toLowerCase();
            const c = (code || '').toLowerCase();
            return n.includes(q) || c.includes(q);
        }
        return true;
    },
    openDelete(id, name) {
        this.deleteMealId = id;
        this.deleteMealName = name;
        $dispatch('open-modal', 'delete-meal-modal');
    },
    updateVisibleCount() {
        this.$nextTick(() => {
            const rows = this.$el.querySelectorAll('tbody tr[data-meal-row]');
            let count = 0;
            rows.forEach(row => {
                if (row.style.display !== 'none') count++;
            });
            this.totalVisible = count;
        });
    }
}"
    x-effect="
    const _c = activeCategory;
    const _s = activeStatus;
    const _q = searchQuery;
    updateVisibleCount();
">
    <!-- Filter Toolbar: Categories on Left, Search & Status on Right (Instant 0ms Alpine Filtering) -->
    <div class="mb-4 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <!-- Category Filter Tabs with Counts -->
        <div class="flex flex-wrap items-center gap-1.5">
            <button type="button" x-on:click="activeCategory = 'all'"
                :class="activeCategory === 'all' ? 'bg-brand-primary text-white shadow-2xs font-semibold' :
                    'bg-brand-bg text-brand-text-muted hover:text-brand-text border border-brand-border'"
                class="rounded-md px-3 py-1.5 text-xs font-semibold transition cursor-pointer">
                All ({{ $totalDishesCount }})
            </button>
            @foreach ($categories as $cat)
                <button type="button" x-on:click="activeCategory = '{{ $cat->slug }}'"
                    :class="activeCategory === '{{ $cat->slug }}' ? 'bg-brand-primary text-white shadow-2xs font-semibold' :
                        'bg-brand-bg text-brand-text-muted hover:text-brand-text border border-brand-border'"
                    class="rounded-md px-3 py-1.5 text-xs font-semibold transition cursor-pointer">
                    {{ $cat->name }} ({{ $cat->meals_count }})
                </button>
            @endforeach
        </div>

        <!-- Search & Status Filters -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Search Input with Instant Alpine Filter -->
            <div class="relative w-full sm:w-56">
                <input type="search" x-model="searchQuery" placeholder="Search dishes or codes..."
                    class="w-full rounded-md border border-brand-border bg-brand-bg px-3.5 py-1.5 pl-8 pr-7 text-xs text-brand-text placeholder:text-brand-text-muted/60 focus:border-brand-primary dark:focus:border-brand-secondary focus:bg-brand-card focus:outline-none focus:ring-1 focus:ring-brand-primary/20 transition">
                <svg class="pointer-events-none absolute left-2.5 top-2 h-3.5 w-3.5 text-brand-text-muted"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <button type="button" x-show="searchQuery" x-on:click="searchQuery = ''" x-cloak
                    class="absolute right-2 top-2 text-brand-text-muted hover:text-brand-text cursor-pointer">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Status Segments (Instant 0ms Alpine Filter) -->
            <div class="inline-flex rounded-md border border-brand-border bg-brand-bg p-0.5 text-xs font-medium">
                <button type="button" x-on:click="activeStatus = 'all'"
                    :class="activeStatus === 'all' ? 'bg-brand-card text-brand-text shadow-2xs font-semibold' :
                        'text-brand-text-muted hover:text-brand-text'"
                    class="rounded-sm px-2.5 py-1 transition cursor-pointer">
                    All
                </button>
                <button type="button" x-on:click="activeStatus = 'in_stock'"
                    :class="activeStatus === 'in_stock' ? 'bg-brand-card text-brand-success shadow-2xs font-semibold' :
                        'text-brand-text-muted hover:text-brand-text'"
                    class="rounded-sm px-2.5 py-1 transition cursor-pointer">
                    In Stock
                </button>
                <button type="button" x-on:click="activeStatus = 'sold_out'"
                    :class="activeStatus === 'sold_out' ? 'bg-brand-card text-brand-error shadow-2xs font-semibold' :
                        'text-brand-text-muted hover:text-brand-text'"
                    class="rounded-sm px-2.5 py-1 transition cursor-pointer">
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
                    <tr wire:key="meal-row-{{ $meal->id }}" data-meal-row x-data="{ isAvail: {{ $meal->is_available ? 'true' : 'false' }} }"
                        x-show="matches('{{ $meal->category?->slug }}', isAvail, '{{ addslashes($meal->name) }}', '{{ addslashes($meal->menu_code ?? '') }}')"
                        :class="{ 'opacity-65 bg-brand-bg/25': !isAvail }" class="transition hover:bg-brand-bg/40">

                        <!-- Meal Name & Notes -->
                        <td class="px-4 py-3 max-w-sm">
                            <div class="font-bold text-brand-text text-sm">{{ $meal->name }}</div>
                            @if ($meal->description)
                                <div class="text-xs text-brand-text-muted line-clamp-1">
                                    {{ $meal->description }}
                                </div>
                            @endif
                            @if ($meal->track_stock && !is_null($meal->stock_quantity))
                                <div
                                    class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-semibold text-brand-secondary">
                                    {{ $meal->stock_quantity }} portions left today
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

                        <!-- Status Toggle (86'd / In Stock - 0ms Optimistic UI) -->
                        <td class="px-3 py-3 text-center">
                            <button type="button"
                                x-on:click="isAvail = !isAvail; $wire.toggleAvailability({{ $meal->id }})"
                                title="Click to toggle availability"
                                :class="isAvail ?
                                    'bg-brand-success/15 text-brand-success border-brand-success/30 hover:bg-brand-success/25' :
                                    'bg-brand-error/15 text-brand-error border-brand-error/30 hover:bg-brand-error/25'"
                                class="inline-flex items-center gap-1.5 rounded-sm px-2.5 py-0.5 text-xs font-semibold transition cursor-pointer border shadow-2xs">
                                <span class="h-1.5 w-1.5 rounded-full"
                                    :class="isAvail ? 'bg-brand-success' : 'bg-brand-error'"></span>
                                <span x-text="isAvail ? 'In Stock' : '86\'d (Sold Out)'"></span>
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
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z">
                                            </path>
                                        </svg>
                                    </span>
                                </button>
                                <button type="button"
                                    x-on:click="openDelete({{ $meal->id }}, '{{ addslashes($meal->name) }}')"
                                    title="Remove dish"
                                    class="rounded-sm px-1.5 py-1 font-semibold text-brand-error/70 hover:text-brand-error hover:bg-brand-error/10 transition cursor-pointer">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-brand-text-muted">
                            No dishes found in the menu catalog.
                        </td>
                    </tr>
                @endforelse

                <!-- Alpine Client-Side Filter Empty State -->
                <tr x-show="totalVisible === 0" x-cloak style="display: none;">
                    <td colspan="6" class="px-4 py-8 text-center text-sm text-brand-text-muted">
                        No dishes found matching your current filter.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Centralized Confirmation Modal for Removing/Archiving Dish -->
    <x-ui.confirm-modal name="delete-meal-modal" title="Remove Dish from Menu" maxWidth="md">
        <p>
            Are you sure you want to remove <strong class="text-brand-text font-bold"
                x-text="deleteMealName"></strong> from the active menu?
        </p>
        <p class="mt-1.5 text-xs text-brand-text-muted">
            Historical sales, kitchen tickets, and reporting records will remain preserved in the database.
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
</section>
