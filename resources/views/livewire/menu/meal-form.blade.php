<section>
    <p class="text-xs text-brand-text-muted mb-4">Enter dish specifications to add or update restaurant menu items.</p>
    <form class="space-y-4" onsubmit="event.preventDefault()">
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-brand-text mb-1.5">Meal / Dish Name</label>
            <input type="text" placeholder="e.g. Truffle Mushroom Risotto" class="w-full rounded-md border border-brand-border bg-brand-bg px-3.5 py-2 text-sm text-brand-text placeholder:text-brand-text-muted/60 focus:border-brand-primary dark:focus:border-brand-secondary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 dark:focus:ring-brand-secondary/20 transition shadow-2xs">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-brand-text mb-1.5">Menu Code (Optional)</label>
                <input type="text" placeholder="e.g. M-01" class="w-full rounded-md border border-brand-border bg-brand-bg px-3.5 py-2 text-sm text-brand-text font-mono placeholder:text-brand-text-muted/60 focus:border-brand-primary dark:focus:border-brand-secondary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 dark:focus:ring-brand-secondary/20 transition shadow-2xs">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-brand-text mb-1.5">Category</label>
                <select class="w-full rounded-md border border-brand-border bg-brand-bg px-3.5 py-2 text-sm text-brand-text focus:border-brand-primary dark:focus:border-brand-secondary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 dark:focus:ring-brand-secondary/20 transition shadow-2xs">
                    <option value="">Select Category</option>
                    <option value="mains">Mains</option>
                    <option value="starters">Starters & Sides</option>
                    <option value="specials">Chef's Specials</option>
                    <option value="beverages">Beverages</option>
                    <option value="desserts">Desserts</option>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-brand-text mb-1.5">Selling Price ($)</label>
            <input type="number" step="0.01" min="0" placeholder="0.00" class="w-full rounded-md border border-brand-border bg-brand-bg px-3.5 py-2 text-sm text-brand-text placeholder:text-brand-text-muted/60 focus:border-brand-primary dark:focus:border-brand-secondary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 dark:focus:ring-brand-secondary/20 transition shadow-2xs">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-brand-text mb-1.5">Kitchen Prep Notes / Description</label>
            <textarea rows="2" placeholder="e.g. Served with shaved parmesan and herb crust. Allergy: Dairy" class="w-full rounded-md border border-brand-border bg-brand-bg px-3.5 py-2 text-sm text-brand-text placeholder:text-brand-text-muted/60 focus:border-brand-primary dark:focus:border-brand-secondary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 dark:focus:ring-brand-secondary/20 transition shadow-2xs"></textarea>
        </div>
        <div class="flex items-center justify-between rounded-md border border-brand-border bg-brand-bg/60 p-3">
            <div>
                <span class="block text-xs font-semibold text-brand-text">Kitchen Availability</span>
                <span class="text-[11px] text-brand-text-muted">Uncheck if dish is 86'd (Sold Out)</span>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" checked class="sr-only peer">
                <div class="w-9 h-5 bg-brand-border peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-brand-border after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand-success"></div>
            </label>
        </div>
        <button type="button" class="w-full rounded-md bg-brand-primary hover:bg-brand-primary-hover text-white py-2.5 text-xs font-semibold uppercase tracking-wider transition shadow-2xs cursor-pointer">
            Save Meal to Menu
        </button>
    </form>
</section>
