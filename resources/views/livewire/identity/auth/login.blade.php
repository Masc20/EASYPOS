<div class="w-full max-w-md">
    <div class="rounded-md bg-brand-card p-7 sm:p-8 shadow-sm border border-brand-border transition-colors duration-200"
        x-data="pinPad({ wire: $wire, maxDigits: 4 })" @keydown.window="handleKeydown($event)">
        <!-- Terminal Header -->
        <div class="mb-6 text-center">
            <div
                class="inline-flex h-12 w-12 items-center justify-center rounded-md bg-brand-primary/10 text-brand-primary dark:text-brand-secondary mb-3 shadow-2xs">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-brand-text">Floor Staff Terminal</h1>
        </div>

        <form wire:submit="login" class="space-y-5">
            <!-- Employee ID Input -->
            <div>
                <label for="emp_id"
                    class="block text-xs font-semibold uppercase tracking-wider text-brand-text mb-1.5">
                    Employee ID
                </label>
                <div class="relative">
                    <input wire:model="emp_id" id="emp_id" type="text" required autofocus
                        class="block w-full uppercase tracking-wider rounded-md border border-brand-border bg-brand-bg/60 px-4 py-3 text-sm font-medium text-brand-text shadow-2xs placeholder:text-brand-text-muted/50 focus:border-brand-primary dark:focus:border-brand-secondary focus:bg-brand-card focus:outline-none focus:ring-2 focus:ring-brand-primary/20 dark:focus:ring-brand-secondary/20 transition"
                        placeholder="e.g. B01-CSH-001" />
                </div>
                @error('emp_id')
                    <span class="mt-1.5 block text-xs font-medium text-brand-error">{{ $message }}</span>
                @enderror
            </div>

            <!-- 4-Digit PIN Visualization & Control -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-brand-text">
                        4-Digit PIN
                    </label>
                    <button type="button" @click="clear()" x-show="pin.length > 0" x-cloak
                        class="text-xs font-semibold text-brand-text-muted hover:text-brand-error transition cursor-pointer">
                        Clear
                    </button>
                </div>

                <!-- Instant 0ms PIN Circles Indicator -->
                <div
                    class="flex items-center justify-center gap-4 py-2 bg-brand-bg/70 rounded-md border border-brand-border">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="h-4 w-4 rounded-full transition-all duration-150"
                            :class="pin.length > {{ $i }} ?
                                'bg-brand-primary ring-4 ring-brand-secondary/30 scale-110' :
                                'border-2 border-brand-border bg-brand-card'">
                        </div>
                    @endfor
                </div>

                <!-- Hidden Input for keyboard/accessibility compatibility -->
                <input x-model="pin" type="password" maxlength="4" inputmode="numeric" pattern="[0-9]*"
                    class="sr-only" id="keyboard-pin-input" />
                @error('pin')
                    <span class="mt-1.5 block text-center text-xs font-medium text-brand-error">{{ $message }}</span>
                @enderror
            </div>

            <!-- Zero-Lag On-Screen Numeric Keypad -->
            <div class="grid grid-cols-3 gap-2.5 pt-1">
                @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $digit)
                    <button type="button" @click="press('{{ $digit }}')"
                        class="flex h-13 items-center justify-center rounded-md border border-brand-border bg-brand-bg/60 text-xl font-bold text-brand-text shadow-2xs transition active:scale-95 active:bg-brand-primary active:text-white active:border-brand-primary hover:bg-brand-secondary/10 hover:border-brand-secondary focus:outline-none focus:ring-2 focus:ring-brand-secondary/25 select-none cursor-pointer">
                        {{ $digit }}
                    </button>
                @endforeach
                <button type="button" @click="clear()"
                    class="flex h-13 items-center justify-center rounded-md border border-brand-border bg-brand-bg/60 text-xs font-bold uppercase tracking-wider text-brand-text-muted shadow-2xs transition active:scale-95 hover:bg-brand-error/10 hover:border-brand-error hover:text-brand-error select-none cursor-pointer">
                    Clear
                </button>
                <button type="button" @click="press('0')"
                    class="flex h-13 items-center justify-center rounded-md border border-brand-border bg-brand-bg/60 text-xl font-bold text-brand-text shadow-2xs transition active:scale-95 active:bg-brand-primary active:text-white active:border-brand-primary hover:bg-brand-secondary/10 hover:border-brand-secondary focus:outline-none focus:ring-2 focus:ring-brand-secondary/25 select-none cursor-pointer">
                    0
                </button>
                <button type="button" @click="backspace()"
                    class="flex h-13 items-center justify-center rounded-md border border-brand-border bg-brand-bg/60 text-brand-text-muted shadow-2xs transition active:scale-95 hover:bg-brand-secondary/10 hover:border-brand-secondary hover:text-brand-secondary select-none cursor-pointer"
                    title="Backspace">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414-6.414a2 2 0 011.414-.586H19a2 2 0 012 2v10a2 2 0 01-2 2h-7.172a2 2 0 01-1.414-.586L3 12z" />
                    </svg>
                </button>
            </div>

            <!-- Submit Button -->
            <div class="pt-2">
                <button type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-md bg-brand-primary hover:bg-brand-primary-hover text-white shadow-sm shadow-brand-primary/30 active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-brand-primary focus:ring-offset-2 transition-all disabled:opacity-50 cursor-pointer py-3.5 text-sm font-semibold">
                    <span wire:loading.remove wire:target="login">Clock In / Enter Terminal</span>
                    <span wire:loading wire:target="login"
                        class="inline-flex flex-row justfy-center justify-center gap-2">
                        <svg class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        Authenticating...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
