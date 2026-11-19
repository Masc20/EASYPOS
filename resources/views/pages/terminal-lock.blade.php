@php
    $hasSelfPin = filled($user->pin_code);
    $hasSelfPassword = filled($user->password);
    $initialMode = old('unlock_mode', 'self');
    $initialOverrideType = old('email') ? 'email' : 'pin';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'EASYPOS') }} - Terminal Locked</title>
    <script>
        document.documentElement.classList.remove('dark');
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="min-h-dvh w-full bg-brand-bg text-brand-text font-sans antialiased selection:bg-brand-primary selection:text-white flex flex-col justify-between relative overflow-x-hidden select-none">

    {{-- Atmospheric Background Layers --}}
    <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
        <img src="{{ asset('image/glow.svg') }}" alt=""
            class="pointer-events-none absolute -top-24 -right-24 w-[65vw] max-w-[680px] opacity-80 select-none" />
        <img src="{{ asset('image/glow.svg') }}" alt=""
            class="pointer-events-none absolute -bottom-28 -left-28 w-[70vw] max-w-[740px] opacity-90 select-none" />
        <img src="{{ asset('image/bg.svg') }}" alt=""
            class="pointer-events-none absolute -bottom-10 -left-10 w-[44vw] max-w-[420px] opacity-40 select-none" />
    </div>

    {{-- Top Status Bar --}}
    <header class="w-full h-13 px-4 sm:px-6 flex items-center justify-between border-b border-brand-border bg-brand-bg/90 backdrop-blur-xs relative z-20 shrink-0">
        <div class="flex items-center gap-2.5">
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-brand-card border border-brand-border shadow-2xs">
                <img src="{{ asset('image/PSB.svg') }}" alt="EASYPOS" class="w-4.5 h-4.5 object-contain" />
            </span>
            <div>
                <span class="font-bold tracking-tight text-brand-text text-sm leading-tight block">{{ config('app.name', 'EASYPOS') }}</span>
                <span class="text-[11px] font-medium text-brand-text-muted tracking-wide block leading-tight">Terminal Lock Screen</span>
            </div>
        </div>

        <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-error/10 border border-brand-error/30 px-3 py-1 text-[11px] font-semibold text-brand-error shadow-2xs whitespace-nowrap">
            <span class="h-2 w-2 rounded-full bg-brand-error"></span>
            Terminal Locked
        </span>
    </header>

    {{-- Main Lock Card --}}
    <main class="w-full flex-1 flex items-center justify-center px-4 py-5 relative z-10">
        <div class="w-full max-w-[430px] bg-brand-card rounded-xl border border-brand-border shadow-md p-5 sm:p-6"
            x-data="window.EASYPOS_TERMINAL_LOCK ? window.EASYPOS_TERMINAL_LOCK() : {}"
            @keydown.window="if (typeof handleKeydown === 'function') handleKeydown($event)">
            <div class="text-center mb-4">
                <div class="inline-flex h-11 w-11 items-center justify-center rounded-lg bg-primary-100 text-brand-primary mb-2.5 shadow-2xs">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
                <h1 class="text-lg sm:text-xl font-bold tracking-tight text-brand-text">Terminal Locked</h1>
                <p class="text-xs text-brand-text-muted mt-1">
                    Locked by <span class="font-semibold text-brand-text">{{ $user->name }}</span>
                    @if ($user->emp_id)
                        <span class="font-mono">({{ $user->emp_id }})</span>
                    @endif
                    @if ($user->branch)
                        &middot; {{ $user->branch->name }}
                    @endif
                </p>
                @if ($lockedAt)
                    <p class="text-[11px] text-brand-text-muted mt-0.5">
                        Since {{ \Illuminate\Support\Carbon::parse($lockedAt)->format('M j, Y g:i A') }}
                    </p>
                @endif
            </div>

            @if ($errors->has('credential'))
                <div class="mb-4 rounded-md border border-brand-error/40 bg-brand-error/10 px-3 py-2 text-xs font-medium text-brand-error">
                    {{ $errors->first('credential') }}
                </div>
            @endif

            {{-- Mode Switcher --}}
            <div class="grid grid-cols-2 gap-1.5 rounded-lg bg-brand-muted p-1 mb-4">
                <button type="button" id="tab-self"
                    class="rounded-md px-3 py-1.5 text-xs font-semibold transition cursor-pointer">
                    Unlock Self
                </button>
                <button type="button" id="tab-override"
                    class="rounded-md px-3 py-1.5 text-xs font-semibold transition cursor-pointer">
                    Manager Override
                </button>
            </div>

            <form method="POST" action="{{ route('terminal.unlock') }}" id="unlock-form" class="space-y-4">
                @csrf
                <input type="hidden" name="unlock_mode" id="unlock-mode-input" value="{{ $initialMode }}">

                {{-- Self Unlock Panel --}}
                <div id="panel-self" class="space-y-3">
                    @if ($hasSelfPin)
                        <div id="self-pin-block" class="space-y-3">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-semibold text-brand-text">Enter Your 4-Digit PIN</label>
                                <div class="flex items-center gap-3">
                                    <button type="button" id="pin-top-clear-btn" x-show="pin && pin.length > 0" x-cloak
                                        @click="clear()"
                                        class="text-xs font-semibold text-brand-text-muted hover:text-brand-primary transition cursor-pointer">
                                        Clear
                                    </button>
                                    @if ($hasSelfPassword)
                                        <button type="button" id="toggle-self-password"
                                            @click="useSelfPassword()"
                                            class="text-xs font-semibold text-brand-primary hover:underline cursor-pointer">
                                            Use Password
                                        </button>
                                    @endif
                                </div>
                            </div>
                            <input type="hidden" name="pin" id="self-pin-input" x-model="pin" value="">
                            <div id="self-pin-dots"
                                class="w-full h-10 flex items-center justify-center gap-6 bg-brand-muted/50 rounded-md border border-brand-border">
                                @for ($i = 0; $i < 4; $i++)
                                    <div class="pin-dot w-3 h-3 rounded-full border-2 border-brand-border bg-brand-card transition-all duration-150"
                                        :class="(pin && pin.length > {{ $i }}) ? 'bg-brand-primary border-brand-primary scale-105' : 'bg-brand-card border-brand-border'"></div>
                                @endfor
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $digit)
                                    <button type="button" data-pin-digit="{{ $digit }}"
                                        @click="press('{{ $digit }}')"
                                        class="h-10 rounded-md border border-brand-border bg-brand-muted/50 text-base font-bold text-brand-text hover:bg-brand-muted active:scale-95 active:bg-brand-primary active:text-white transition cursor-pointer">
                                        {{ $digit }}
                                    </button>
                                @endforeach
                                <button type="button" id="pin-clear-btn"
                                    @click="clear()"
                                    class="h-10 rounded-md border border-brand-border bg-brand-muted/50 text-xs font-bold uppercase text-brand-text-muted hover:text-brand-primary active:scale-95 transition cursor-pointer">
                                    Clear
                                </button>
                                <button type="button" data-pin-digit="0"
                                    @click="press('0')"
                                    class="h-10 rounded-md border border-brand-border bg-brand-muted/50 text-base font-bold text-brand-text hover:bg-brand-muted active:scale-95 active:bg-brand-primary active:text-white transition cursor-pointer">
                                    0
                                </button>
                                <button type="button" id="pin-back-btn"
                                    @click="backspace()"
                                    class="h-10 rounded-md border border-brand-border bg-brand-muted/50 text-xs font-bold uppercase text-brand-text-muted hover:text-brand-primary active:scale-95 transition cursor-pointer">
                                    Del
                                </button>
                            </div>
                        </div>
                    @endif

                    @if ($hasSelfPassword)
                        <div id="self-password-block" class="{{ $hasSelfPin ? 'hidden' : '' }} space-y-2">
                            <div class="flex items-center justify-between">
                                <label for="self-password-input" class="block text-xs font-semibold text-brand-text">Account Password</label>
                                @if ($hasSelfPin)
                                    <button type="button" id="toggle-self-pin"
                                        class="text-xs font-semibold text-brand-primary hover:underline cursor-pointer">
                                        Use PIN
                                    </button>
                                @endif
                            </div>
                            <input type="password" name="password" id="self-password-input" autocomplete="current-password"
                                class="block w-full rounded-md border border-brand-border bg-brand-muted/50 px-3 py-2 text-sm text-brand-text focus:outline-none focus:ring-2 focus:ring-brand-primary"
                                placeholder="Enter your password" />
                        </div>
                    @endif
                </div>

                {{-- Manager / Executive Override Panel --}}
                <div id="panel-override" class="hidden space-y-3">
                    <div class="flex gap-2 text-xs">
                        <button type="button" id="override-type-pin"
                            class="flex-1 rounded-md border border-brand-border px-2.5 py-1.5 font-semibold cursor-pointer">
                            Employee ID + PIN
                        </button>
                        <button type="button" id="override-type-email"
                            class="flex-1 rounded-md border border-brand-border px-2.5 py-1.5 font-semibold cursor-pointer">
                            Email + Password
                        </button>
                    </div>

                    <div id="override-pin-fields" class="space-y-3">
                        <div>
                            <label for="override-emp-id" class="block text-xs font-semibold text-brand-text mb-1">Manager / Owner Employee ID</label>
                            <input type="text" name="emp_id" id="override-emp-id" value="{{ old('emp_id') }}"
                                class="block w-full uppercase tracking-wider rounded-md border border-brand-border bg-brand-muted/50 px-3 py-2 text-sm font-medium text-brand-text focus:outline-none focus:ring-2 focus:ring-brand-primary"
                                placeholder="e.g. B01-MGR-001" />
                        </div>
                        <div>
                            <label for="override-pin" class="block text-xs font-semibold text-brand-text mb-1">Manager 4-Digit PIN</label>
                            <input type="password" name="pin" id="override-pin" maxlength="4" inputmode="numeric" pattern="[0-9]{4}"
                                class="block w-full tracking-[0.4em] rounded-md border border-brand-border bg-brand-muted/50 px-3 py-2 text-sm font-bold text-brand-text focus:outline-none focus:ring-2 focus:ring-brand-primary"
                                placeholder="••••" />
                        </div>
                    </div>

                    <div id="override-email-fields" class="hidden space-y-3">
                        <div>
                            <label for="override-email" class="block text-xs font-semibold text-brand-text mb-1">Manager / Executive Email</label>
                            <input type="email" name="email" id="override-email" value="{{ old('email') }}"
                                class="block w-full rounded-md border border-brand-border bg-brand-muted/50 px-3 py-2 text-sm text-brand-text focus:outline-none focus:ring-2 focus:ring-brand-primary"
                                placeholder="manager@easypos.com" />
                        </div>
                        <div>
                            <label for="override-password" class="block text-xs font-semibold text-brand-text mb-1">Password</label>
                            <input type="password" name="password" id="override-password" autocomplete="current-password"
                                class="block w-full rounded-md border border-brand-border bg-brand-muted/50 px-3 py-2 text-sm text-brand-text focus:outline-none focus:ring-2 focus:ring-brand-primary"
                                placeholder="Enter password" />
                        </div>
                    </div>
                </div>

                <button type="submit" id="unlock-submit-btn"
                    class="w-full h-10 bg-brand-primary hover:bg-brand-primary-hover active:bg-primary-800 text-white text-sm font-semibold rounded-md shadow-xs transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-75 disabled:cursor-not-allowed">
                    <svg id="unlock-submit-spinner" class="hidden animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                        fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    <span id="unlock-submit-label">Unlock Terminal</span>
                </button>
            </form>

            {{-- Clock Out / Sign Out Escape Hatch --}}
            <div class="mt-4 pt-4 border-t border-brand-border flex items-center justify-between gap-3">
                <span class="text-xs text-brand-text-muted">Need to switch staff?</span>
                <form method="POST" action="{{ route('logout') }}" id="lock-logout-form" class="inline">
                    @csrf
                    <button type="submit" id="lock-logout-btn"
                        class="rounded-md border border-brand-border bg-brand-card px-3 py-1.5 text-xs font-semibold text-brand-text hover:border-brand-error hover:text-brand-error transition cursor-pointer disabled:opacity-60">
                        {{ $isFloorContext ? 'Clock Out' : 'Sign Out' }}
                    </button>
                </form>
            </div>
        </div>
    </main>

    <footer class="w-full py-2 text-center text-[11px] text-brand-text-muted relative z-20 shrink-0">
        <span>&copy; {{ date('Y') }} {{ config('app.name', 'EASYPOS') }} &middot; Protected Terminal Session</span>
    </footer>

    <script>
        (function () {
            history.pushState(null, document.title, location.href);
            window.addEventListener('popstate', function () {
                history.pushState(null, document.title, location.href);
            });
            window.addEventListener('pageshow', function (event) {
                if (event.persisted) {
                    window.location.reload();
                }
            });

            const modeInput = document.getElementById('unlock-mode-input');
            const tabSelf = document.getElementById('tab-self');
            const tabOverride = document.getElementById('tab-override');
            const panelSelf = document.getElementById('panel-self');
            const panelOverride = document.getElementById('panel-override');

            const selfPinBlock = document.getElementById('self-pin-block');
            const selfPasswordBlock = document.getElementById('self-password-block');
            const selfPinInput = document.getElementById('self-pin-input');
            const selfPasswordInput = document.getElementById('self-password-input');
            const toggleSelfPassword = document.getElementById('toggle-self-password');
            const toggleSelfPin = document.getElementById('toggle-self-pin');

            const overrideTypePinBtn = document.getElementById('override-type-pin');
            const overrideTypeEmailBtn = document.getElementById('override-type-email');
            const overridePinFields = document.getElementById('override-pin-fields');
            const overrideEmailFields = document.getElementById('override-email-fields');
            const overrideEmpId = document.getElementById('override-emp-id');
            const overridePin = document.getElementById('override-pin');
            const overrideEmail = document.getElementById('override-email');
            const overridePassword = document.getElementById('override-password');

            let currentMode = @json($initialMode);
            let selfMethod = @json($hasSelfPin ? 'pin' : 'password');
            let overrideMethod = @json($initialOverrideType);

            function syncActiveFields() {
                modeInput.value = currentMode;
                const isSelf = currentMode === 'self';

                panelSelf.classList.toggle('hidden', !isSelf);
                panelOverride.classList.toggle('hidden', isSelf);

                tabSelf.className = isSelf
                    ? 'rounded-md px-3 py-1.5 text-xs font-semibold bg-brand-card text-brand-primary shadow-2xs cursor-pointer'
                    : 'rounded-md px-3 py-1.5 text-xs font-semibold text-brand-text-muted hover:text-brand-text cursor-pointer';
                tabOverride.className = !isSelf
                    ? 'rounded-md px-3 py-1.5 text-xs font-semibold bg-brand-card text-brand-primary shadow-2xs cursor-pointer'
                    : 'rounded-md px-3 py-1.5 text-xs font-semibold text-brand-text-muted hover:text-brand-text cursor-pointer';

                if (selfPinInput) {
                    selfPinInput.disabled = !(isSelf && selfMethod === 'pin');
                }
                if (selfPasswordInput) {
                    selfPasswordInput.disabled = !(isSelf && selfMethod === 'password');
                }
                if (selfPinBlock && selfPasswordBlock) {
                    selfPinBlock.classList.toggle('hidden', selfMethod !== 'pin');
                    selfPasswordBlock.classList.toggle('hidden', selfMethod !== 'password');
                }

                const useOverridePin = !isSelf && overrideMethod === 'pin';
                const useOverrideEmail = !isSelf && overrideMethod === 'email';

                overridePinFields.classList.toggle('hidden', overrideMethod !== 'pin');
                overrideEmailFields.classList.toggle('hidden', overrideMethod !== 'email');
                overrideTypePinBtn.classList.toggle('border-brand-primary', overrideMethod === 'pin');
                overrideTypePinBtn.classList.toggle('text-brand-primary', overrideMethod === 'pin');
                overrideTypeEmailBtn.classList.toggle('border-brand-primary', overrideMethod === 'email');
                overrideTypeEmailBtn.classList.toggle('text-brand-primary', overrideMethod === 'email');

                overrideEmpId.disabled = !useOverridePin;
                overridePin.disabled = !useOverridePin;
                overrideEmail.disabled = !useOverrideEmail;
                overridePassword.disabled = !useOverrideEmail;
            }

            tabSelf.addEventListener('click', () => { currentMode = 'self'; syncActiveFields(); });
            tabOverride.addEventListener('click', () => { currentMode = 'override'; syncActiveFields(); });
            if (toggleSelfPassword) {
                toggleSelfPassword.addEventListener('click', () => { selfMethod = 'password'; syncActiveFields(); });
            }
            if (toggleSelfPin) {
                toggleSelfPin.addEventListener('click', () => { selfMethod = 'pin'; syncActiveFields(); });
            }
            overrideTypePinBtn.addEventListener('click', () => { overrideMethod = 'pin'; syncActiveFields(); });
            overrideTypeEmailBtn.addEventListener('click', () => { overrideMethod = 'email'; syncActiveFields(); });

            const dots = document.querySelectorAll('#self-pin-dots .pin-dot');
            const unlockForm = document.getElementById('unlock-form');
            const unlockBtn = document.getElementById('unlock-submit-btn');
            const unlockSpinner = document.getElementById('unlock-submit-spinner');
            const unlockLabel = document.getElementById('unlock-submit-label');
            let isSubmitting = false;

            function setUnlockLoadingState() {
                if (unlockBtn) {
                    unlockBtn.disabled = true;
                }
                if (unlockSpinner) {
                    unlockSpinner.classList.remove('hidden');
                }
                if (unlockLabel) {
                    unlockLabel.textContent = 'Unlocking...';
                }
            }

            function submitUnlockForm() {
                if (isSubmitting || !unlockForm) return;
                isSubmitting = true;
                setUnlockLoadingState();
                unlockForm.submit();
            }

            if (unlockForm) {
                unlockForm.addEventListener('submit', function (e) {
                    if (isSubmitting) {
                        e.preventDefault();
                        return;
                    }
                    isSubmitting = true;
                    setUnlockLoadingState();
                });
            }

            function renderPinDots() {
                if (!selfPinInput) return;
                const len = selfPinInput.value.length;
                dots.forEach((dot, idx) => {
                    dot.classList.toggle('bg-brand-primary', idx < len);
                    dot.classList.toggle('border-brand-primary', idx < len);
                    dot.classList.toggle('scale-105', idx < len);
                    dot.classList.toggle('bg-brand-card', idx >= len);
                    dot.classList.toggle('border-brand-border', idx >= len);
                });
            }

            function pressDigit(digit) {
                if (!selfPinInput || isSubmitting) return;
                if (currentMode !== 'self' || selfMethod !== 'pin') return;
                if (selfPinInput.value.length >= 4) return;

                selfPinInput.value += String(digit);
                selfPinInput.dispatchEvent(new Event('input'));
                renderPinDots();

                if (selfPinInput.value.length === 4) {
                    submitUnlockForm();
                }
            }

            function backspaceDigit() {
                if (!selfPinInput || isSubmitting) return;
                if (currentMode !== 'self' || selfMethod !== 'pin') return;
                if (selfPinInput.value.length === 0) return;

                selfPinInput.value = selfPinInput.value.slice(0, -1);
                selfPinInput.dispatchEvent(new Event('input'));
                renderPinDots();
            }

            function clearDigits() {
                if (!selfPinInput || isSubmitting) return;
                selfPinInput.value = '';
                selfPinInput.dispatchEvent(new Event('input'));
                renderPinDots();
            }

            function handleGlobalKeydown(event) {
                if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey) {
                    return;
                }

                const activeEl = document.activeElement;
                const targetTag = ((event.target && event.target.tagName) || (activeEl && activeEl.tagName) || '').toUpperCase();
                const isTextInputFocused = (targetTag === 'INPUT' || targetTag === 'TEXTAREA' || targetTag === 'SELECT')
                    && event.target !== selfPinInput;

                if (isTextInputFocused) {
                    return;
                }

                if (currentMode === 'self' && selfMethod === 'pin' && selfPinInput) {
                    if (/^[0-9]$/.test(event.key)) {
                        event.preventDefault();
                        pressDigit(event.key);
                    } else if (event.key === 'Backspace') {
                        event.preventDefault();
                        backspaceDigit();
                    } else if (event.key === 'Escape' || event.key === 'Delete') {
                        event.preventDefault();
                        clearDigits();
                    } else if (event.key === 'Enter' && selfPinInput.value.length === 4) {
                        event.preventDefault();
                        submitUnlockForm();
                    }
                }
            }

            // Expose Alpine-compatible controller while avoiding double-binding when Alpine is active
            window.EASYPOS_TERMINAL_LOCK = function () {
                return {
                    pin: selfPinInput ? selfPinInput.value : '',
                    maxDigits: 4,
                    isProcessing: false,
                    init() {
                        window.__easyposLockAlpineActive = true;
                        if (selfPinInput) {
                            selfPinInput.addEventListener('input', () => {
                                this.pin = selfPinInput.value;
                            });
                        }
                    },
                    press(digit) {
                        pressDigit(digit);
                        this.pin = selfPinInput ? selfPinInput.value : '';
                    },
                    backspace() {
                        backspaceDigit();
                        this.pin = selfPinInput ? selfPinInput.value : '';
                    },
                    clear() {
                        clearDigits();
                        this.pin = '';
                    },
                    useSelfPassword() {
                        selfMethod = 'password';
                        syncActiveFields();
                        if (selfPasswordInput) selfPasswordInput.focus();
                    },
                    handleKeydown(event) {
                        handleGlobalKeydown(event);
                        this.pin = selfPinInput ? selfPinInput.value : '';
                    },
                };
            };

            document.querySelectorAll('[data-pin-digit]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (window.__easyposLockAlpineActive) return;
                    pressDigit(btn.getAttribute('data-pin-digit'));
                });
            });

            const clearBtn = document.getElementById('pin-clear-btn');
            const topClearBtn = document.getElementById('pin-top-clear-btn');
            const backBtn = document.getElementById('pin-back-btn');
            if (clearBtn && selfPinInput) {
                clearBtn.addEventListener('click', () => {
                    if (window.__easyposLockAlpineActive) return;
                    clearDigits();
                });
            }
            if (topClearBtn && selfPinInput) {
                topClearBtn.addEventListener('click', () => {
                    if (window.__easyposLockAlpineActive) return;
                    clearDigits();
                });
            }
            if (backBtn && selfPinInput) {
                backBtn.addEventListener('click', () => {
                    if (window.__easyposLockAlpineActive) return;
                    backspaceDigit();
                });
            }

            window.addEventListener('keydown', function (event) {
                if (window.__easyposLockAlpineActive) return;
                handleGlobalKeydown(event);
            });

            if (overridePin) {
                overridePin.addEventListener('input', function () {
                    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);
                });
            }

            const lockLogoutForm = document.getElementById('lock-logout-form');
            const lockLogoutBtn = document.getElementById('lock-logout-btn');
            if (lockLogoutForm && lockLogoutBtn) {
                lockLogoutForm.addEventListener('submit', function (e) {
                    if (lockLogoutForm.dataset.submitting === '1') {
                        e.preventDefault();
                        return;
                    }
                    lockLogoutForm.dataset.submitting = '1';
                    lockLogoutBtn.disabled = true;
                    lockLogoutBtn.textContent = @json($isFloorContext ? 'Clocking out...' : 'Signing out...');
                });
            }

            syncActiveFields();
            renderPinDots();
        })();
    </script>
    @livewireScripts
</body>

</html>
