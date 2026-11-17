<div class="w-full max-w-[420px] mx-auto select-none" aria-label="Floor Staff Terminal">
    {{-- Card Container --}}
    <div class="w-full bg-brand-card rounded-xl border border-brand-border shadow-sm sm:shadow-md p-5 sm:p-6 transition-all"
        x-data="pinPad({ wire: $wire, maxDigits: 4 })" @keydown.window="handleKeydown($event)">
        {{-- Card Header --}}
        <div class="text-center mb-4">
            {{-- Icon Badge --}}
            <div
                class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary-100 text-brand-primary mb-2.5 shadow-2xs">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="3" width="7" height="7" rx="1.5" stroke="currentColor"
                        stroke-width="1.8" />
                    <rect x="14" y="3" width="7" height="7" rx="1.5" stroke="currentColor"
                        stroke-width="1.8" />
                    <rect x="3" y="14" width="7" height="7" rx="1.5" stroke="currentColor"
                        stroke-width="1.8" />
                    <rect x="5" y="5" width="3" height="3" fill="currentColor" />
                    <rect x="16" y="5" width="3" height="3" fill="currentColor" />
                    <rect x="5" y="16" width="3" height="3" fill="currentColor" />
                    <rect x="14" y="14" width="3" height="3" fill="currentColor" />
                    <rect x="18" y="18" width="3" height="3" fill="currentColor" />
                </svg>
            </div>
            <h1 class="text-lg sm:text-xl font-bold tracking-tight text-brand-text">Floor Staff Login</h1>
            <p class="text-xs text-brand-text-muted mt-0.5">Enter your employee ID and 4-digit PIN</p>
        </div>

        {{-- Login Form --}}
        <form wire:submit="login" class="space-y-3">
            {{-- Employee ID Input --}}
            <div>
                <label for="emp_id" class="block text-xs font-semibold text-brand-text mb-1">
                    Employee ID
                </label>
                <div class="relative">
                    <input wire:model="emp_id" id="emp_id" type="text" required autofocus autocomplete="username"
                        class="block w-full uppercase tracking-wider rounded-md border @error('emp_id') border-brand-error ring-1 ring-brand-error @else border-brand-border @enderror bg-brand-muted/50 px-3 py-2 text-sm font-medium text-brand-text shadow-2xs placeholder:text-brand-text-muted/60 focus:outline-none focus:ring-2 focus:ring-brand-primary focus:border-transparent transition"
                        placeholder="e.g. B01-CSH-001" />
                </div>
                @error('emp_id')
                    <span class="mt-1 block text-xs font-medium text-brand-error">{{ $message }}</span>
                @enderror
            </div>

            {{-- 4-Digit PIN Visualization & Control --}}
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-semibold text-brand-text">
                        4-Digit PIN
                    </label>
                    <button type="button" @click="clear()" x-show="pin.length > 0" x-cloak
                        class="text-xs font-semibold text-brand-text-muted hover:text-brand-primary transition cursor-pointer">
                        Clear
                    </button>
                </div>

                {{-- Instant 0ms PIN Circles Indicator --}}
                <div
                    class="w-full h-9 flex items-center justify-center gap-6 bg-brand-muted/50 rounded-md border @error('pin') border-brand-error ring-1 ring-brand-error @else border-brand-border @enderror">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="w-3 h-3 rounded-full transition-all duration-150"
                            :class="pin.length > {{ $i }} ?
                                'bg-brand-primary border-2 border-brand-primary scale-105' :
                                'border-2 border-brand-border bg-brand-card'">
                        </div>
                    @endfor
                </div>

                {{-- Hidden Input for keyboard/accessibility compatibility --}}
                <input x-model="pin" type="password" maxlength="4" inputmode="numeric" pattern="[0-9]*"
                    class="sr-only" id="keyboard-pin-input" tabindex="-1" />
                @error('pin')
                    <span class="mt-1 block text-center text-xs font-medium text-brand-error">{{ $message }}</span>
                @enderror
            </div>

            {{-- Zero-Lag On-Screen Numeric Keypad --}}
            <div class="grid grid-cols-3 gap-2 pt-0.5">
                @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $digit)
                    <button type="button" @click="press('{{ $digit }}')"
                        class="flex h-9.5 sm:h-10 items-center justify-center rounded-md border border-brand-border bg-brand-muted/50 text-base sm:text-lg font-bold text-brand-text shadow-2xs transition hover:bg-brand-muted active:scale-95 active:bg-brand-primary active:text-white active:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20 select-none cursor-pointer">
                        {{ $digit }}
                    </button>
                @endforeach

                {{-- Row 4: Clear, 0, Backspace --}}
                <button type="button" @click="clear()"
                    class="flex h-9.5 sm:h-10 items-center justify-center rounded-md border border-brand-border bg-brand-muted/50 text-xs font-bold uppercase tracking-wider text-brand-text-muted shadow-2xs transition hover:bg-brand-muted hover:text-brand-primary active:scale-95 focus:outline-none focus:ring-2 focus:ring-brand-primary/20 select-none cursor-pointer">
                    Clear
                </button>
                <button type="button" @click="press('0')"
                    class="flex h-9.5 sm:h-10 items-center justify-center rounded-md border border-brand-border bg-brand-muted/50 text-base sm:text-lg font-bold text-brand-text shadow-2xs transition hover:bg-brand-muted active:scale-95 active:bg-brand-primary active:text-white active:border-brand-primary focus:outline-none focus:ring-2 focus:ring-brand-primary/20 select-none cursor-pointer">
                    0
                </button>
                <button type="button" @click="backspace()"
                    class="flex h-9.5 sm:h-10 items-center justify-center rounded-md border border-brand-border bg-brand-muted/50 text-brand-text-muted shadow-2xs transition hover:bg-brand-muted hover:text-brand-primary active:scale-95 focus:outline-none focus:ring-2 focus:ring-brand-primary/20 select-none cursor-pointer"
                    title="Backspace" aria-label="Backspace">
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"></path>
                        <line x1="18" y1="9" x2="12" y2="15"></line>
                        <line x1="12" y1="9" x2="18" y2="15"></line>
                    </svg>
                </button>
            </div>

            {{-- Submit Action Button --}}
            <div class="pt-1">
                <button type="submit" wire:loading.attr="disabled"
                    class="w-full h-10 bg-brand-primary hover:bg-brand-primary-hover active:bg-primary-800 text-white text-sm font-semibold rounded-md shadow-xs transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-75 disabled:cursor-not-allowed">
                    <svg wire:loading wire:target="login" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                        fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    <span wire:loading.remove wire:target="login">Enter Terminal</span>
                    <span wire:loading wire:target="login">Authenticating...</span>
                </button>
            </div>
        </form>
    </div>
</div>
