<div class="space-y-1.5 w-full">
    @if ($label)
        <label class="block text-xs font-semibold uppercase tracking-wider text-brand-text">
            {{ $label }}
        </label>
    @endif

    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-bold text-brand-text-muted">
            {{ $currencySymbol }}
        </span>
        <input type="number"
            wire:model.live.debounce.300ms="amount"
            step="{{ $step }}"
            min="{{ $min }}"
            @if ($max !== null) max="{{ $max }}" @endif
            @disabled($disabled)
            class="w-full rounded-md border border-brand-border bg-brand-bg px-3.5 py-2 pl-8 font-mono text-sm font-bold text-brand-text placeholder:text-brand-text-muted/60 focus:border-brand-primary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 transition shadow-2xs">
    </div>

    @if (!$disabled)
        <div class="flex items-center gap-1 pt-1">
            <button type="button"
                wire:click="addAmount(50)"
                class="rounded-sm border border-brand-border bg-brand-card px-2 py-0.5 font-mono text-[10px] font-semibold text-brand-text hover:bg-brand-bg transition cursor-pointer">
                +{{ $currencySymbol }}50
            </button>
            <button type="button"
                wire:click="addAmount(100)"
                class="rounded-sm border border-brand-border bg-brand-card px-2 py-0.5 font-mono text-[10px] font-semibold text-brand-text hover:bg-brand-bg transition cursor-pointer">
                +{{ $currencySymbol }}100
            </button>
            <button type="button"
                wire:click="addAmount(500)"
                class="rounded-sm border border-brand-border bg-brand-card px-2 py-0.5 font-mono text-[10px] font-semibold text-brand-text hover:bg-brand-bg transition cursor-pointer">
                +{{ $currencySymbol }}500
            </button>
            <button type="button"
                wire:click="setAmount(0)"
                class="rounded-sm border border-brand-border bg-brand-card px-2 py-0.5 text-[10px] font-semibold text-brand-text-muted hover:text-brand-error hover:bg-brand-bg transition cursor-pointer ml-auto">
                Reset
            </button>
        </div>
    @endif
</div>
