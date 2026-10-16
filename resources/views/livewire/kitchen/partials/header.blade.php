<div
    class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-brand-card p-4 rounded-md border border-brand-border shadow-2xs">
    <div class="flex items-center gap-2.5">
        <h1 class="text-xl font-black uppercase tracking-tight text-brand-text">Kitchen Display System (KDS)</h1>
        <span
            class="inline-flex items-center gap-1.5 rounded-sm bg-brand-success/15 border border-brand-success/30 px-2 py-0.5 text-xs font-bold text-brand-success">
            <span class="h-2 w-2 rounded-full bg-brand-success animate-pulse"></span>
            Live Queue ({{ $counts['total_active'] }})
        </span>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <div class="inline-flex rounded-md border border-brand-border bg-brand-bg p-0.5 shadow-2xs">
            <button type="button" x-on:click="diningFilter = 'all'"
                :class="diningFilter === 'all' ? 'bg-brand-primary text-white' :
                    'text-brand-text-muted hover:text-brand-text'"
                class="rounded-sm px-2.5 py-1 text-xs font-semibold transition cursor-pointer">
                All
            </button>
            <button type="button" x-on:click="diningFilter = 'dine_in'"
                :class="diningFilter === 'dine_in' ? 'bg-brand-primary text-white' :
                    'text-brand-text-muted hover:text-brand-text'"
                class="rounded-sm px-2.5 py-1 text-xs font-semibold transition cursor-pointer">
                Dine In
            </button>
            <button type="button" x-on:click="diningFilter = 'takeout'"
                :class="diningFilter === 'takeout' ? 'bg-brand-primary text-white' :
                    'text-brand-text-muted hover:text-brand-text'"
                class="rounded-sm px-2.5 py-1 text-xs font-semibold transition cursor-pointer">
                Takeout
            </button>
        </div>

        <button type="button" x-on:click="priorityFilter = (priorityFilter === 'rush' ? 'all' : 'rush')"
            :class="priorityFilter === 'rush' ? 'bg-brand-error text-white border-brand-error shadow-2xs' :
                'border-brand-border bg-brand-bg text-brand-text-muted hover:text-brand-text'"
            class="rounded-md border px-2.5 py-1 text-xs font-bold transition cursor-pointer inline-flex items-center gap-1">
            <span> Rush Only</span>
        </button>

        <button type="button" wire:click="toggleAudioAlert"
            class="rounded-md border px-2.5 py-1 text-xs font-semibold transition cursor-pointer inline-flex items-center gap-1.5 {{ $audioAlertEnabled ? 'border-brand-border bg-brand-bg text-brand-text' : 'border-brand-border bg-brand-card text-brand-text-muted line-through' }}"
            title="{{ $audioAlertEnabled ? 'Chime sound is active on new orders' : 'Sound alerts muted' }}">
            <svg class="h-3.5 w-3.5 {{ $audioAlertEnabled ? 'text-amber-500' : 'text-brand-text-muted' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span>{{ $audioAlertEnabled ? 'Chime On' : 'Muted' }}</span>
        </button>

        <button type="button" x-on:click="showRecallDrawer = true"
            class="rounded-md border border-brand-border bg-brand-card hover:bg-brand-bg px-2.5 py-1 text-xs font-semibold text-brand-text transition cursor-pointer inline-flex items-center gap-1 shadow-2xs">
            <svg class="h-3.5 w-3.5 text-brand-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Recall History</span>
        </button>
    </div>
</div>
