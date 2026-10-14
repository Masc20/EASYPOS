@props([
    'variant' => 'neutral', // success, error/danger, warning, primary, neutral
    'dot' => false,
])

@php
    $variantClasses = match ($variant) {
        'success' => 'bg-brand-success/15 text-brand-success border-brand-success/30',
        'danger', 'error' => 'bg-brand-error/15 text-brand-error border-brand-error/30',
        'warning' => 'bg-brand-secondary/15 text-brand-secondary border-brand-secondary/30',
        'primary' => 'bg-brand-primary/10 text-brand-primary dark:text-brand-secondary border-brand-primary/20',
        default => 'bg-brand-bg text-brand-text-muted border-brand-border',
    };

    $dotColor = match ($variant) {
        'success' => 'bg-brand-success',
        'danger', 'error' => 'bg-brand-error',
        'warning' => 'bg-brand-secondary',
        'primary' => 'bg-brand-primary dark:bg-brand-secondary',
        default => 'bg-brand-text-muted',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-semibold border $variantClasses"]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 rounded-full {{ $dotColor }}"></span>
    @endif
    {{ $slot }}
</span>
