@props([
    'name',
    'title' => null,
    'maxWidth' => 'lg',
])

@php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        default => 'sm:max-w-lg',
    };
@endphp

<div x-data="{ show: false, name: '{{ $name }}' }"
    x-show="show"
    x-on:open-modal.window="
        let d = $event.detail;
        let target = typeof d === 'string' ? d : (Array.isArray(d) ? d[0] : (d?.name || null));
        if (target === name) show = true;
    "
    x-on:close-modal.window="
        let d = $event.detail;
        let target = typeof d === 'string' ? d : (Array.isArray(d) ? d[0] : (d?.name || null));
        if (target === name) show = false;
    "
    x-on:keydown.escape.window="show = false"
    x-cloak
    class="relative z-50"
    role="dialog"
    aria-modal="true">

    <!-- Backdrop with fast 150ms client transition (zero server lag) -->
    <div x-show="show"
        x-transition:enter="ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-on:click="show = false"
        class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"></div>

    <!-- Centered dialog container -->
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-3 sm:p-4 text-center">
            <div x-show="show"
                x-transition:enter="ease-out duration-150"
                x-transition:enter-start="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-95"
                class="relative w-full {{ $maxWidthClass }} transform rounded-lg bg-brand-card text-left shadow-2xl transition-all border border-brand-border overflow-hidden">
                
                @if ($title)
                    <div class="flex items-center justify-between border-b border-brand-border px-5 py-3.5 bg-brand-bg/60">
                        <h3 class="text-base font-bold text-brand-text">{{ $title }}</h3>
                        <button type="button"
                            x-on:click="show = false"
                            class="rounded-md p-1 text-brand-text-muted hover:text-brand-text hover:bg-brand-bg transition cursor-pointer">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                @endif

                <div class="p-5">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</div>
