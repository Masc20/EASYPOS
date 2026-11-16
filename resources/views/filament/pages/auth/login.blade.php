<div class="relative min-h-dvh w-full bg-brand-bg flex items-center justify-center p-4 sm:p-6 overflow-x-hidden select-none">
    {{-- Atmospheric Background Layers --}}
    <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
        {{-- Top-Right Atmospheric Glow --}}
        <img
            src="{{ asset('image/glow.svg') }}"
            alt=""
            class="pointer-events-none absolute -top-24 -right-24 w-[65vw] max-w-[680px] opacity-80 select-none"
        />
        <div
            class="pointer-events-none absolute -top-[15%] -right-[10%] w-[55vw] max-w-[680px] aspect-square rounded-full"
            style="background: radial-gradient(circle, color-mix(in srgb, var(--color-primary) 22%, transparent) 0%, transparent 70%);"
        ></div>

        {{-- Bottom-Left Atmospheric Glow --}}
        <img
            src="{{ asset('image/glow.svg') }}"
            alt=""
            class="pointer-events-none absolute -bottom-28 -left-28 w-[70vw] max-w-[740px] opacity-90 select-none"
        />
        <div
            class="pointer-events-none absolute -bottom-[20%] -left-[10%] w-[60vw] max-w-[740px] aspect-square rounded-full"
            style="background: radial-gradient(circle, color-mix(in srgb, var(--color-primary) 28%, transparent) 0%, transparent 70%);"
        ></div>

        {{-- Culinary Illustration Asset Clusters (bg.svg) --}}
        <img
            src="{{ asset('image/bg.svg') }}"
            alt=""
            class="pointer-events-none absolute -bottom-10 -left-10 w-[44vw] max-w-[420px] opacity-45 select-none transform origin-bottom-left"
        />
        <img
            src="{{ asset('image/bg.svg') }}"
            alt=""
            class="pointer-events-none absolute -top-8 -right-8 w-[36vw] max-w-[340px] opacity-45 select-none transform origin-top-right rotate-180 sm:rotate-0"
        />
    </div>

    {{-- Centered Admin Login Card --}}
    <div class="relative z-10 w-full max-w-[440px] bg-brand-card rounded-xl border border-brand-border shadow-sm sm:shadow-md p-6 sm:p-8 transition-all">
        {{-- Card Header --}}
        <div class="text-center mb-6">
            <h1 class="text-xl font-bold text-brand-text tracking-tight">EASYPOS Admin</h1>
            <h2 class="text-lg font-semibold text-brand-text mt-1">Sign in</h2>
        </div>

        @if (filled($this->userUndertakingMultiFactorAuthentication))
            {{-- Multi-Factor Challenge Fallback --}}
            <form wire:submit.prevent="authenticate" class="space-y-4">
                {{ $this->multiFactorChallengeForm }}

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="w-full h-10 sm:h-11 bg-brand-primary hover:bg-brand-primary-hover active:bg-primary-800 text-white text-sm font-semibold rounded-md shadow-xs transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-75 disabled:cursor-not-allowed"
                >
                    <svg wire:loading wire:target="authenticate" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="authenticate">Verify & Sign in</span>
                    <span wire:loading wire:target="authenticate">Verifying...</span>
                </button>
            </form>
        @else
            {{-- Primary Admin Login Form --}}
            <form wire:submit.prevent="authenticate" class="space-y-4">
                {{-- Email Address Field --}}
                <div>
                    <label for="data.email" class="block text-xs font-semibold text-brand-text mb-1.5">
                        Email address
                    </label>
                    <input
                        type="email"
                        wire:model="data.email"
                        id="data.email"
                        autocomplete="email"
                        autofocus
                        required
                        class="w-full h-10.5 px-3.5 bg-brand-card border @error('data.email') border-brand-error ring-1 ring-brand-error @else border-brand-border @enderror rounded-md text-sm text-brand-text placeholder-brand-text-muted/60 focus:outline-none focus:ring-2 focus:ring-brand-primary focus:border-transparent transition"
                    />
                    @error('data.email')
                        <p class="mt-1.5 text-xs text-brand-error font-medium">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password Field with Eye Reveal Toggle --}}
                <div x-data="{ showPassword: false }">
                    <label for="data.password" class="block text-xs font-semibold text-brand-text mb-1.5">
                        Password
                    </label>
                    <div class="relative flex items-stretch rounded-md border @error('data.password') border-brand-error ring-1 ring-brand-error @else border-brand-border @enderror bg-brand-card focus-within:ring-2 focus-within:ring-brand-primary focus-within:border-transparent transition overflow-hidden">
                        <input
                            :type="showPassword ? 'text' : 'password'"
                            wire:model="data.password"
                            id="data.password"
                            autocomplete="current-password"
                            required
                            class="flex-1 h-10 px-3.5 bg-transparent border-0 text-sm text-brand-text placeholder-brand-text-muted/60 focus:outline-none focus:ring-0"
                        />
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="flex items-center justify-center w-10 border-l border-brand-border bg-brand-card hover:bg-brand-muted text-brand-text-muted transition cursor-pointer"
                            tabindex="-1"
                            aria-label="Toggle password visibility"
                        >
                            {{-- Eye Open (masked) --}}
                            <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            {{-- Eye Closed / Slash (unmasked) --}}
                            <svg x-show="showPassword" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                            </svg>
                        </button>
                    </div>
                    @error('data.password')
                        <p class="mt-1.5 text-xs text-brand-error font-medium">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Remember Me Checkbox --}}
                <div class="flex items-center gap-2 pt-0.5">
                    <input
                        type="checkbox"
                        wire:model="data.remember"
                        id="data.remember"
                        class="w-[17px] h-[17px] rounded-[3px] border-brand-border text-brand-primary focus:ring-brand-primary focus:ring-offset-0 cursor-pointer"
                    />
                    <label for="data.remember" class="text-xs text-brand-text cursor-pointer select-none">
                        Remember me
                    </label>
                </div>

                {{-- Submit Action Button --}}
                <div class="pt-1">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="w-full h-10 sm:h-11 bg-brand-primary hover:bg-brand-primary-hover active:bg-primary-800 text-white text-sm font-semibold rounded-md shadow-xs transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-75 disabled:cursor-not-allowed"
                    >
                        <svg wire:loading wire:target="authenticate" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="authenticate">Sign in</span>
                        <span wire:loading wire:target="authenticate">Signing in...</span>
                    </button>
                </div>
            </form>
        @endif

        {{-- Divider & Floor Staff Terminal Navigation Link --}}
        <div class="mt-6 pt-5 border-t border-brand-border text-center">
            <p class="text-xs text-brand-text-muted">Floor Staff (Cashier, Cook, Chef)?</p>
            <a
                href="{{ route('login') }}"
                class="mt-1.5 inline-flex items-center justify-center text-xs font-semibold text-brand-primary hover:text-brand-primary-hover hover:underline transition"
            >
                &larr; Switch to Floor Staff Terminal (Emp ID + PIN)
            </a>
        </div>
    </div>
</div>
