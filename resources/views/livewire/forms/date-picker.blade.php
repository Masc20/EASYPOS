<div class="space-y-1.5 w-full">
    @if ($label)
        <label class="block text-xs font-semibold uppercase tracking-wider text-brand-text">
            {{ $label }}
        </label>
    @endif

    <div class="flex items-center gap-1.5">
        <div class="relative flex-1">
            <input type="date"
                wire:model.live="selectedDate"
                class="w-full rounded-md border border-brand-border bg-brand-bg px-3 py-1.5 pl-8 text-xs font-mono text-brand-text focus:border-brand-primary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 transition shadow-2xs">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-brand-text-muted">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
        </div>

        <div class="flex items-center gap-1 shrink-0">
            <button type="button"
                wire:click="applyPreset('today')"
                class="rounded-sm border px-2 py-1.5 text-[11px] font-semibold transition cursor-pointer {{ $preset === 'today' ? 'bg-brand-primary text-white border-brand-primary' : 'bg-brand-card border-brand-border text-brand-text-muted hover:text-brand-text hover:bg-brand-bg' }}">
                Today
            </button>
            <button type="button"
                wire:click="applyPreset('yesterday')"
                class="rounded-sm border px-2 py-1.5 text-[11px] font-semibold transition cursor-pointer {{ $preset === 'yesterday' ? 'bg-brand-primary text-white border-brand-primary' : 'bg-brand-card border-brand-border text-brand-text-muted hover:text-brand-text hover:bg-brand-bg' }}">
                Yesterday
            </button>
        </div>
    </div>
</div>
