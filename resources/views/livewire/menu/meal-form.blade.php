<x-ui.modal name="meal-modal" :title="$mealId ? 'Edit Dish' : 'Add New Dish'" maxWidth="lg">
    <form wire:submit="save" x-data="{ isAvailable: @entangle('is_available'), trackStock: @entangle('track_stock') }" class="space-y-4">
        @include('livewire.menu.partials.meal-form-fields')

        <div class="pt-3 border-t border-brand-border flex items-center justify-end gap-2.5">
            <x-ui.button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'meal-modal')">
                Cancel
            </x-ui.button>
            <x-ui.button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ $mealId ? 'Update Dish' : 'Create Dish' }}</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-1.5">
                    <svg class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    Saving...
                </span>
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>
