@props([
    'label' => null,
    'error' => null,
    'prefix' => null,
    'required' => false,
])

<div class="w-full">
    @if ($label)
        <label class="block text-xs font-semibold uppercase tracking-wider text-brand-text mb-1.5">
            {{ $label }}
            @if ($required)
                <span class="text-brand-error">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        @if ($prefix)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-xs font-semibold text-brand-text-muted">
                {{ $prefix }}
            </span>
        @endif

        <input {{ $attributes->merge(['class' => 'w-full rounded-md border border-brand-border bg-brand-bg px-3.5 py-2 text-sm text-brand-text placeholder:text-brand-text-muted/60 focus:border-brand-primary dark:focus:border-brand-secondary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 dark:focus:ring-brand-secondary/20 transition shadow-2xs ' . ($prefix ? 'pl-8 ' : '') . ($error ? 'border-brand-error ring-1 ring-brand-error/30 ' : '')]) }}>
    </div>

    @if ($error)
        <span class="mt-1 block text-xs font-medium text-brand-error">{{ $error }}</span>
    @endif
</div>
