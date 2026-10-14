<x-ui.modal name="edit-meal-modal" title="Edit Dish" maxWidth="lg">
    <form wire:submit="save" x-data="{
        isAvailable: @entangle('is_available'),
        trackStock: @entangle('track_stock')
    }" class="space-y-4">
        <!-- Dish Name -->
        <x-ui.input label="Meal / Dish Name" name="name" wire:model="name" placeholder="e.g. Truffle Mushroom Risotto"
            :required="true" :error="$errors->first('name')" />

        <!-- Category & Menu Code -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-brand-text mb-1.5">
                    Category <span class="text-brand-error">*</span>
                </label>
                <select wire:model="category_id"
                    class="w-full rounded-md border border-brand-border bg-brand-bg px-3.5 py-2 text-sm text-brand-text focus:border-brand-primary dark:focus:border-brand-secondary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 transition shadow-2xs">
                    <option value="">Select Category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id')
                    <span class="mt-1 block text-xs font-medium text-brand-error">{{ $message }}</span>
                @enderror
            </div>

            <x-ui.input label="Menu Code (optional)" name="menu_code" wire:model="menu_code" placeholder="e.g. M-01"
                class="uppercase font-mono" :error="$errors->first('menu_code')" />
        </div>

        <!-- Pricing (Selling Price Only) -->
        <x-ui.input label="Selling Price ({{ $currencySymbol }})" name="price" type="number" step="0.01"
            min="0" wire:model="price" placeholder="0.00" :prefix="$currencySymbol" :required="true"
            class="font-mono font-semibold" :error="$errors->first('price')" />

        <!-- Description -->
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-brand-text mb-1.5">
                Prep Notes / Description <span class="text-brand-text-muted font-normal lowercase">(optional)</span>
            </label>
            <textarea rows="2" wire:model="description"
                placeholder="e.g. Served with shaved parmesan. Allergy alert: Contains dairy."
                class="w-full rounded-md border border-brand-border bg-brand-bg px-3.5 py-2 text-sm text-brand-text placeholder:text-brand-text-muted/60 focus:border-brand-primary dark:focus:border-brand-secondary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 transition shadow-2xs"></textarea>
            @error('description')
                <span class="mt-1 block text-xs font-medium text-brand-error">{{ $message }}</span>
            @enderror
        </div>

        <!-- Availability Toggle (Instant 0ms Client-Side Alpine Switch) -->
        <div class="grid grid-cols-2 gap-3 pt-1">
            <button type="button" x-on:click="isAvailable = true"
                :class="isAvailable ? 'border-brand-success bg-brand-success/10 text-brand-success font-semibold' :
                    'border-brand-border bg-brand-bg/40 opacity-60 text-brand-text-muted'"
                class="flex items-center gap-2 rounded-md border p-2.5 text-left transition cursor-pointer">
                <span class="h-2 w-2 rounded-full" :class="isAvailable ? 'bg-brand-success' : 'bg-brand-border'"></span>
                <span class="text-xs">In Stock</span>
            </button>
            <button type="button" x-on:click="isAvailable = false"
                :class="!isAvailable ? 'border-brand-error bg-brand-error/10 text-brand-error font-semibold' :
                    'border-brand-border bg-brand-bg/40 opacity-60 text-brand-text-muted'"
                class="flex items-center gap-2 rounded-md border p-2.5 text-left transition cursor-pointer">
                <span class="h-2 w-2 rounded-full" :class="!isAvailable ? 'bg-brand-error' : 'bg-brand-border'"></span>
                <span class="text-xs">Sold Out (86'd)</span>
            </button>
        </div>

        <!-- Daily Limited Portions (Instant 0ms Client-Side Alpine Toggle) -->
        <div class="rounded-md border border-brand-border bg-brand-bg/40 p-3 space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-brand-text">Track Daily Limited Portions</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" x-model="trackStock" class="sr-only peer">
                    <div
                        class="w-8 h-4.5 bg-brand-border peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-brand-border after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-brand-secondary">
                    </div>
                </label>
            </div>
            <div x-show="trackStock" x-cloak class="pt-2 border-t border-brand-border/60 flex items-center gap-3">
                <label class="text-xs text-brand-text-muted shrink-0">Portions Today:</label>
                <input type="number" min="0" wire:model="stock_quantity" placeholder="e.g. 15"
                    class="w-28 rounded-md border border-brand-border bg-brand-card px-2.5 py-1 text-xs text-brand-text font-mono font-bold focus:border-brand-primary dark:focus:border-brand-secondary focus:outline-none">
                @error('stock_quantity')
                    <span class="text-xs font-medium text-brand-error">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <!-- Modal Footer Actions with Centralized x-ui.button -->
        <div class="pt-3 border-t border-brand-border flex items-center justify-end gap-2.5">
            <x-ui.button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'edit-meal-modal')">
                Cancel
            </x-ui.button>
            <x-ui.button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Update Dish</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-1.5">
                    <svg class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    Updating...
                </span>
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>
