@props([
    'padding' => 'p-5 sm:p-6',
])

<div {{ $attributes->merge(['class' => "rounded-lg border border-brand-border bg-brand-card text-brand-text shadow-xs $padding"]) }}>
    {{ $slot }}
</div>
