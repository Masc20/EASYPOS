@props([
    'name',
    'title' => 'Confirm Action',
    'maxWidth' => 'sm',
])

<x-ui.modal :name="$name" :maxWidth="$maxWidth">
    <div class="sm:flex sm:items-start gap-4">
        <div class="mx-auto flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-brand-error/15 text-brand-error sm:mx-0">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        <div class="mt-3 text-center sm:mt-0 sm:text-left flex-1">
            <h3 class="text-base font-bold text-brand-text">{{ $title }}</h3>
            <div class="mt-2 text-xs text-brand-text-muted leading-relaxed">
                {{ $slot }}
            </div>
        </div>
    </div>

    <div class="mt-5 pt-3 border-t border-brand-border flex items-center justify-end gap-2.5">
        <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', '{{ $name }}')">
            Cancel
        </x-ui.button>
        {{ $action ?? '' }}
    </div>
</x-ui.modal>
