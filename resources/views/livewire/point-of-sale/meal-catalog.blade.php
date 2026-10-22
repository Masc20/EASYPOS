<div x-data="{
    activeCategory: @entangle('selectedCategoryId'),
    showSoldOutOnly: false,
    searchQuery: '',
    matches(categoryId, text, isSoldOut) {
        if (this.showSoldOutOnly && !isSoldOut) {
            return false;
        }
        const matchesCat = this.activeCategory === null || this.activeCategory == categoryId;
        const matchesSearch = !this.searchQuery || text.toLowerCase().includes(this.searchQuery.toLowerCase());
        return matchesCat && matchesSearch;
    },
    filterCategory(id) {
        this.showSoldOutOnly = false;
        this.activeCategory = id;
    },
    filterSoldOut() {
        this.showSoldOutOnly = !this.showSoldOutOnly;
        if (this.showSoldOutOnly) {
            this.activeCategory = null;
        }
    }
}" x-on:catalog-search.window="searchQuery = $event.detail.query" class="space-y-4">

    <!-- Category Filter Tabs & 86'd Quick Filter (Instant 0ms Client-Side Alpine Switch) -->
    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-thin">
        <button type="button" x-on:click="filterCategory(null)"
            :class="(activeCategory === null && !showSoldOutOnly) ? 'bg-brand-primary text-white border-brand-primary shadow-2xs' :
                'border-brand-border bg-brand-bg text-brand-text-muted hover:text-brand-text hover:bg-brand-card'"
            class="shrink-0 px-3 py-1.5 text-xs font-semibold rounded-md transition cursor-pointer select-none border">
            All Dishes
        </button>
        @foreach ($categories as $cat)
            <button type="button" x-on:click="filterCategory({{ $cat->id }})"
                :class="(activeCategory == {{ $cat->id }} && !showSoldOutOnly) ? 'bg-brand-primary text-white border-brand-primary shadow-2xs' :
                    'border-brand-border bg-brand-bg text-brand-text-muted hover:text-brand-text hover:bg-brand-card'"
                class="shrink-0 px-3 py-1.5 text-xs font-semibold rounded-md transition cursor-pointer select-none border">
                {{ $cat->name }}
            </button>
        @endforeach

        <!-- Sold Out (86'd) Quick Filter Button -->
        <button type="button" x-on:click="filterSoldOut()"
            :class="showSoldOutOnly ? 'bg-brand-error text-white border-brand-error shadow-2xs' :
                'border-brand-border bg-brand-bg text-brand-error hover:bg-brand-error/10 hover:border-brand-error/40'"
            class="shrink-0 px-3 py-1.5 text-xs font-semibold rounded-md transition cursor-pointer select-none border inline-flex items-center gap-1.5 ml-auto">
            <span class="inline-block h-2 w-2 rounded-full" :class="showSoldOutOnly ? 'bg-white' : 'bg-brand-error'"></span>
            <span>Sold Out (86'd)</span>
            <span :class="showSoldOutOnly ? 'bg-white/20 text-white' : 'bg-brand-error/15 text-brand-error'"
                class="ml-0.5 rounded-full px-1.5 py-0.5 text-[10px] font-bold">
                {{ $soldOutCount }}
            </span>
        </button>
    </div>

    <!-- Product Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
        @forelse ($meals as $meal)
            @php
                $isSoldOut = $meal->isSoldOut();
                $searchableText = addslashes(strtolower($meal->name . ' ' . ($meal->menu_code ?? '')));
            @endphp
            <div x-show="matches({{ $meal->category_id }}, '{{ $searchableText }}', {{ $isSoldOut ? 'true' : 'false' }})"
                x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                @if (!$isSoldOut)
                    x-on:click="$dispatch('add-to-cart-client', { id: {{ $meal->id }}, name: '{{ addslashes($meal->name) }}', code: '{{ addslashes($meal->menu_code ?? '') }}', price: {{ (float) $meal->price }}, maxQty: {{ $meal->track_stock ? $meal->stock_quantity : 'null' }} })"
                    role="button"
                    tabindex="0"
                    onkeydown="if(event.key === 'Enter' || event.key === ' ') { event.preventDefault(); $dispatch('add-to-cart-client', { id: {{ $meal->id }}, name: '{{ addslashes($meal->name) }}', code: '{{ addslashes($meal->menu_code ?? '') }}', price: {{ (float) $meal->price }}, maxQty: {{ $meal->track_stock ? $meal->stock_quantity : 'null' }} }); }"
                @else
                    x-on:click="$dispatch('pos-alert', { type: 'warning', message: 'Item \'{{ addslashes($meal->name) }}\' is 86\'d (Sold Out) and cannot be added.' })"
                    role="button"
                    tabindex="0"
                    aria-disabled="true"
                @endif
                class="group relative flex flex-col justify-between rounded-md border p-3 transition select-none text-left
                    {{ $isSoldOut
                        ? 'border-brand-error/30 bg-brand-bg/50 grayscale opacity-60 cursor-not-allowed'
                        : 'border-brand-border bg-brand-card hover:border-brand-primary/50 hover:shadow-xs active:scale-[0.98] cursor-pointer' }}">

                <div>
                    <!-- Code & Stock Badges -->
                    <div class="flex items-center justify-between gap-1.5 mb-1.5">
                        @if ($meal->menu_code)
                            <span
                                class="inline-block rounded-sm bg-brand-bg px-1.5 py-0.5 text-[10px] font-mono font-bold text-brand-text-muted border border-brand-border">
                                {{ $meal->menu_code }}
                            </span>
                        @else
                            <span></span>
                        @endif

                        @if ($isSoldOut)
                            <span
                                class="inline-flex items-center gap-1 rounded-sm bg-brand-error/15 border border-brand-error/40 px-1.5 py-0.5 text-[10px] font-bold text-brand-error uppercase tracking-wider">
                                <svg class="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                                86'd / Sold Out
                            </span>
                        @elseif ($meal->track_stock)
                            <span
                                class="inline-flex items-center rounded-sm {{ $meal->stock_quantity <= 5 ? 'bg-amber-500/15 border-amber-500/30 text-amber-500' : 'bg-brand-success/15 border-brand-success/30 text-brand-success' }} px-1.5 py-0.5 text-[10px] font-bold">
                                {{ $meal->stock_quantity }} left
                            </span>
                        @endif
                    </div>

                    <!-- Meal Name -->
                    <h4
                        class="text-xs font-semibold text-brand-text leading-snug line-clamp-2 group-hover:text-brand-primary dark:group-hover:text-brand-secondary transition-colors">
                        {{ $meal->name }}
                    </h4>
                </div>

                <!-- Footer: Price & Add Icon / Sold Out Indicator -->
                <div class="mt-3 pt-2 border-t border-brand-border/50 flex items-center justify-between">
                    <span class="font-mono text-sm font-bold {{ $isSoldOut ? 'text-brand-text-muted line-through' : 'text-brand-text' }}">
                        ₱{{ number_format($meal->price, 2) }}
                    </span>

                    @if (!$isSoldOut)
                        <span
                            class="h-6 w-6 flex items-center justify-center rounded-md bg-brand-primary/10 text-brand-primary group-hover:bg-brand-primary group-hover:text-white transition">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                        </span>
                    @else
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-brand-error bg-brand-error/10 px-1.5 py-0.5 rounded border border-brand-error/20">
                            Unavailable
                        </span>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center">
                <svg class="mx-auto h-10 w-10 text-brand-text-muted/40 mb-2" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <p class="text-sm font-medium text-brand-text">No meals in menu catalog</p>
                <p class="text-xs text-brand-text-muted mt-0.5">Dishes created by the Chef will appear here.</p>
            </div>
        @endforelse
    </div>
</div>
