<div>
    <label class="block text-xs font-semibold uppercase tracking-wider text-brand-text mb-1.5" for="product-search">Find a product</label>
    <div class="relative">
        <input id="product-search" class="w-full rounded-md border border-brand-border bg-brand-bg px-4 py-2.5 pl-10 text-sm text-brand-text placeholder:text-brand-text-muted/60 focus:border-brand-primary dark:focus:border-brand-secondary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 dark:focus:ring-brand-secondary/20 transition shadow-2xs" type="search" wire:model.live="query" placeholder="Scan barcode or type product name...">
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-brand-text-muted">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
    </div>
</div>
