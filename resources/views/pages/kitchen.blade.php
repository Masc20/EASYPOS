<x-layout.app-shell>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-brand-text">Kitchen Display System (KDS)</h1>
            <p class="text-sm text-brand-text-muted">Live incoming order tickets, preparation queue, and order
                fulfillment</p>
        </div>
        <div class="flex items-center gap-3">
            <span
                class="inline-flex items-center gap-1.5 rounded-full bg-brand-success/10 border border-brand-success/30 px-3 py-1 text-xs font-semibold text-brand-success">
                <span class="h-2 w-2 rounded-full bg-brand-success animate-pulse"></span>
                Kitchen Display Active
            </span>
            <span
                class="rounded-md bg-brand-card border border-brand-border px-3 py-1 text-xs font-mono font-semibold text-brand-text shadow-2xs">
                Station: Main Kitchen
            </span>
        </div>
    </div>

    <!-- Kitchen Ticket Pipeline Columns -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- New Orders Column -->
        <div class="rounded-md border border-brand-border bg-brand-card p-5 shadow-2xs flex flex-col">
            <div class="flex items-center justify-between pb-3 mb-4 border-b border-brand-border">
                <div class="flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-brand-secondary"></span>
                    <h2 class="text-base font-bold text-brand-text">New Orders</h2>
                </div>
                <span class="rounded-md bg-brand-secondary/15 text-brand-secondary px-2 py-0.5 text-xs font-bold">
                    0 Tickets
                </span>
            </div>
            <div class="flex-1 flex flex-col items-center justify-center py-12 text-center">
                <div
                    class="h-10 w-10 rounded-full bg-brand-bg flex items-center justify-center text-brand-text-muted mb-2">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-brand-text">No pending tickets</p>
                <p class="text-xs text-brand-text-muted mt-1">New orders from POS terminals will appear here</p>
            </div>
        </div>

        <!-- In Preparation Column -->
        <div class="rounded-md border border-brand-border bg-brand-card p-5 shadow-2xs flex flex-col">
            <div class="flex items-center justify-between pb-3 mb-4 border-b border-brand-border">
                <div class="flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-brand-accent"></span>
                    <h2 class="text-base font-bold text-brand-text">In Preparation</h2>
                </div>
                <span class="rounded-md bg-brand-accent/20 text-brand-text px-2 py-0.5 text-xs font-bold">
                    0 Cooking
                </span>
            </div>
            <div class="flex-1 flex flex-col items-center justify-center py-12 text-center">
                <div
                    class="h-10 w-10 rounded-full bg-brand-bg flex items-center justify-center text-brand-text-muted mb-2">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-brand-text">No active items cooking</p>
                <p class="text-xs text-brand-text-muted mt-1">Tap a ticket to claim and start cooking</p>
            </div>
        </div>

        <!-- Ready to Serve Column -->
        <div class="rounded-md border border-brand-border bg-brand-card p-5 shadow-2xs flex flex-col">
            <div class="flex items-center justify-between pb-3 mb-4 border-b border-brand-border">
                <div class="flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-brand-success"></span>
                    <h2 class="text-base font-bold text-brand-text">Ready to Serve</h2>
                </div>
                <span class="rounded-md bg-brand-success/15 text-brand-success px-2 py-0.5 text-xs font-bold">
                    0 Ready
                </span>
            </div>
            <div class="flex-1 flex flex-col items-center justify-center py-12 text-center">
                <div
                    class="h-10 w-10 rounded-full bg-brand-bg flex items-center justify-center text-brand-text-muted mb-2">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-brand-text">All orders served</p>
                <p class="text-xs text-brand-text-muted mt-1">Completed tickets will await runner pickup</p>
            </div>
        </div>
    </div>
</x-layout.app-shell>
