@props([
    'variant' => 'primary', // primary, secondary, danger, ghost, accent
    'size' => 'md', // sm, md, lg
    'type' => 'button',
])

@php
    $baseClasses = 'inline-flex items-center justify-center font-semibold transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed select-none';

    $sizeClasses = match ($size) {
        'sm' => 'px-2.5 py-1 text-xs rounded-sm gap-1.5',
        'lg' => 'px-5 py-2.5 text-base rounded-md gap-2',
        default => 'px-4 py-2 text-xs rounded-md gap-2 uppercase tracking-wider',
    };

    $variantClasses = match ($variant) {
        'secondary' => 'border border-brand-border bg-brand-card hover:bg-brand-bg text-brand-text shadow-2xs',
        'danger' => 'bg-brand-error hover:bg-brand-error/90 text-white shadow-2xs',
        'ghost' => 'text-brand-text-muted hover:text-brand-text hover:bg-brand-bg',
        'accent' => 'bg-brand-secondary hover:bg-brand-secondary-hover text-white shadow-2xs',
        default => 'bg-brand-primary hover:bg-brand-primary-hover text-white shadow-2xs',
    };
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => "$baseClasses $sizeClasses $variantClasses"]) }}>
    {{ $slot }}
</button>
