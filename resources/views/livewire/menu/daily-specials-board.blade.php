<div class="rounded-md border border-brand-border bg-brand-card px-4 py-3 text-sm text-brand-text shadow-2xs">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <!-- Title & Featured Icon (Star instead of Warning) -->
        <div class="flex items-center gap-2.5">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-brand-primary text-white shadow-2xs">
                <!-- Star / Featured Special Icon -->
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                </svg>
            </span>
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-bold text-xs text-brand-text">Daily Specials &amp; Kitchen Availability</span>
                @if ($soldOutCount > 0)
                    <span class="inline-flex items-center gap-1 rounded-sm bg-brand-error/10 border border-brand-error/20 px-2 py-0.5 text-[11px] font-semibold text-brand-error">
                        {{ $soldOutCount }} {{ Str::plural('dish', $soldOutCount) }} 86'd
                    </span>
                @endif
            </div>
        </div>

        <!-- Limited Batches -->
        @if ($limitedSpecials->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($limitedSpecials as $special)
                    <div class="inline-flex items-center gap-1.5 rounded-sm border border-brand-border bg-brand-bg px-2.5 py-1 text-xs shadow-2xs">
                        <span class="font-medium text-brand-text">{{ $special->name }}</span>
                        <span class="font-bold text-brand-secondary">({{ $special->stock_quantity }} left)</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
