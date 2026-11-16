@props([
    'title' => 'No items found',
    'description' => null,
    'icon' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-xl border border-dashed border-brand-border bg-brand-card/60 px-4 py-12 text-center select-none']) }}>
    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full border border-brand-border bg-brand-muted text-brand-primary dark:text-brand-secondary">
        @if ($icon ?? false)
            {{ $icon }}
        @else
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
            </svg>
        @endif
    </div>
    <h3 class="text-sm font-semibold text-brand-text">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-xs leading-relaxed text-brand-text-muted">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-4 flex items-center justify-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
