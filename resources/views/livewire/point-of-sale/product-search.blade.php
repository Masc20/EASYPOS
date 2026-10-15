<div x-data="{ query: @entangle('query') }">
    <div class="relative">
        <input id="product-search"
            class="w-full rounded-md border border-brand-border bg-brand-card px-4 py-2.5 pl-10 text-sm text-brand-text placeholder:text-brand-text-muted/60 focus:border-brand-primary dark:focus:border-brand-secondary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 dark:focus:ring-brand-secondary/20 transition shadow-2xs font-medium"
            type="search" x-model="query" x-on:input="$dispatch('catalog-search', { query: query })"
            x-on:keydown.enter.prevent="$wire.scanOrSubmit()"
            placeholder="Search meals or enter dish code (e.g. M-01) and hit Enter...">
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-brand-text-muted">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </div>
        <template x-if="query">
            <button type="button"
                x-on:click="query = ''; $dispatch('catalog-search', { query: '' }); $wire.clearSearch();"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-xs text-brand-text-muted hover:text-brand-text transition cursor-pointer">
                ✕
            </button>
        </template>
    </div>
</div>
