<div x-data="{
    activeCategory: @entangle('selectedCategoryId').live,
    searchQuery: '',
    matches(categoryId, text) {
        const matchesCat = this.activeCategory === null || this.activeCategory == categoryId;
        const matchesSearch = !this.searchQuery || text.toLowerCase().includes(this.searchQuery.toLowerCase());
        return matchesCat && matchesSearch;
    }
}" x-on:catalog-search.window="searchQuery = $event.detail.query" class="space-y-4">

    <!-- Category Filter Tabs (Instant 0ms Client-Side Alpine Switch) -->
    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-thin">
        <button type="button" x-on:click="activeCategory = null"
            :class="activeCategory === null ? 'bg-brand-primary text-white border-brand-primary shadow-2xs' :
                'border-brand-border bg-brand-bg text-brand-text-muted hover:text-brand-text hover:bg-brand-card'"
            class="shrink-0 px-3 py-1.5 text-xs font-semibold rounded-md transition cursor-pointer select-none border">
            All Dishes
        </button>
        @foreach ($categories as $cat)
            <button type="button" x-on:click="activeCategory = {{ $cat->id }}"
                :class="activeCategory == {{ $cat->id }} ? 'bg-brand-primary text-white border-brand-primary shadow-2xs' :
                    'border-brand-border bg-brand-bg text-brand-text-muted hover:text-brand-text hover:bg-brand-card'"
                class="shrink-0 px-3 py-1.5 text-xs font-semibold rounded-md transition cursor-pointer select-none border">
                {{ $cat->name }}
            </button>
        @endforeach
    </div>

    <!-- Product Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
        @forelse ($meals as $meal)
            @php
                $isSoldOut = !$meal->is_available || ($meal->track_stock && $meal->stock_quantity <= 0);
                $searchableText = addslashes(strtolower($meal->name . ' ' . ($meal->menu_code ?? '')));
            @endphp
            <div x-show="matches({{ $meal->category_id }}, '{{ $searchableText }}')"
                x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                @if (!$isSoldOut) x-on:click="$dispatch('add-to-cart-client', { id: {{ $meal->id }}, name: '{{ addslashes($meal->name) }}', code: '{{ addslashes($meal->menu_code ?? '') }}', price: {{ (float) $meal->price }}, maxQty: {{ $meal->track_stock ? $meal->stock_quantity : 'null' }} })"
                    role="button"
                    tabindex="0"
                    onkeydown="if(event.key === 'Enter' || event.key === ' ') { event.preventDefault(); $dispatch('add-to-cart-client', { id: {{ $meal->id }}, name: '{{ addslashes($meal->name) }}', code: '{{ addslashes($meal->menu_code ?? '') }}', price: {{ (float) $meal->price }}, maxQty: {{ $meal->track_stock ? $meal->stock_quantity : 'null' }} }); }" @endif
                class="group relative flex flex-col justify-between rounded-md border p-3 transition select-none text-left
                    {{ $isSoldOut
                        ? 'border-brand-border/60 bg-brand-bg/40 opacity-55 cursor-not-allowed'
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
                                class="inline-flex items-center rounded-sm bg-brand-error/15 border border-brand-error/30 px-1.5 py-0.5 text-[10px] font-bold text-brand-error uppercase tracking-wider">
                                86'd
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

                <!-- Footer: Price & Add Icon -->
                <div class="mt-3 pt-2 border-t border-brand-border/50 flex items-center justify-between">
                    <span class="font-mono text-sm font-bold text-brand-text">
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
