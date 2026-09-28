<section>
    <p class="text-xs text-brand-text-muted mb-4">Enter product specifications to update or create catalog items.</p>
    <form class="space-y-4" onsubmit="event.preventDefault()">
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-brand-text mb-1.5">Product Name</label>
            <input type="text" placeholder="e.g. Espresso Blend 250g" class="w-full rounded-md border border-brand-border bg-brand-bg px-3.5 py-2 text-sm text-brand-text placeholder:text-brand-text-muted/60 focus:border-brand-primary dark:focus:border-brand-secondary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 dark:focus:ring-brand-secondary/20 transition shadow-2xs">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-brand-text mb-1.5">SKU / Barcode</label>
            <input type="text" placeholder="e.g. 890123456789" class="w-full rounded-md border border-brand-border bg-brand-bg px-3.5 py-2 text-sm text-brand-text font-mono placeholder:text-brand-text-muted/60 focus:border-brand-primary dark:focus:border-brand-secondary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 dark:focus:ring-brand-secondary/20 transition shadow-2xs">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-brand-text mb-1.5">Unit Price ($)</label>
                <input type="number" step="0.01" min="0" placeholder="0.00" class="w-full rounded-md border border-brand-border bg-brand-bg px-3.5 py-2 text-sm text-brand-text placeholder:text-brand-text-muted/60 focus:border-brand-primary dark:focus:border-brand-secondary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 dark:focus:ring-brand-secondary/20 transition shadow-2xs">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-brand-text mb-1.5">Stock Qty</label>
                <input type="number" min="0" placeholder="0" class="w-full rounded-md border border-brand-border bg-brand-bg px-3.5 py-2 text-sm text-brand-text placeholder:text-brand-text-muted/60 focus:border-brand-primary dark:focus:border-brand-secondary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 dark:focus:ring-brand-secondary/20 transition shadow-2xs">
            </div>
        </div>
        <button type="button" class="w-full rounded-md bg-brand-primary hover:bg-brand-primary-hover text-white py-2.5 text-xs font-semibold uppercase tracking-wider transition shadow-2xs cursor-pointer">
            Save Product
        </button>
    </form>
</section>
