<x-layout.app-shell>
    <!-- Top Header: Single Clean Title & Add Dish Button (Instant 0ms Open) -->
    <div class="mb-5 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-bold tracking-tight text-brand-text">Meal Catalog</h1>
        </div>
        <x-ui.button x-data x-on:click="$dispatch('open-modal', 'create-meal-modal')" variant="primary">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add New Dish
        </x-ui.button>
    </div>

    <!-- Daily Specials Notice -->
    <div class="mb-5">
        <livewire:menu.daily-specials-board />
    </div>

    <!-- Main Full-Width Catalog in Centralized Card -->
    <x-ui.card padding="p-5">
        <livewire:menu.meal-catalog-table />
    </x-ui.card>

    <!-- Dedicated Create Meal Modal (Always Pristine & Blank, 0ms Open) -->
    <livewire:menu.create-meal-modal />

    <!-- Dedicated Edit Meal Modal (Hydrates Specific Meal on Demand) -->
    <livewire:menu.edit-meal-modal />

    <!-- Toast Alert Notification Container for Meal Catalog Errors & Notices -->
    <div x-data="{
        toasts: [],
        add(detail) {
            const data = Array.isArray(detail) ? detail[0] : detail;
            const toast = typeof data === 'object' && data !== null ? data : { message: String(data), type: 'error' };
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message: toast.message || 'Notification', type: toast.type || 'error' });
            setTimeout(() => this.remove(id), 4500);
        },
        remove(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        }
    }" x-on:menu-alert.window="add($event.detail)"
        class="fixed top-5 right-5 z-[9999] flex flex-col gap-2.5 max-w-sm pointer-events-none"
        role="status" aria-live="polite">
        <template x-for="t in toasts" :key="t.id">
            <div class="pointer-events-auto flex items-center gap-2.5 px-4 py-3 rounded-lg border shadow-xl text-xs font-semibold transition-all transform ease-out duration-200"
                :class="t.type === 'error' ? 'bg-brand-error text-white border-brand-error shadow-brand-error/20' :
                    (t.type === 'success' ? 'bg-emerald-600 text-white border-emerald-700 shadow-emerald-600/20' :
                        (t.type === 'warning' ? 'bg-amber-600 text-white border-amber-700 shadow-amber-600/20' :
                            'bg-brand-card text-brand-text border-brand-border shadow-lg'))">
                <template x-if="t.type === 'success'">
                    <svg class="h-4 w-4 shrink-0 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                </template>
                <template x-if="t.type === 'error'">
                    <svg class="h-4 w-4 shrink-0 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </template>
                <template x-if="t.type !== 'success' && t.type !== 'error'">
                    <svg class="h-4 w-4 shrink-0 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                            clip-rule="evenodd" />
                    </svg>
                </template>
                <span x-text="t.message" class="leading-tight"></span>
            </div>
        </template>
    </div>

    <script>
        (function () {
            const registerMenuAvailabilityHooks = () => {
                if (window.__easyposMenuAvailabilityHooksRegistered || !window.Livewire || typeof window.Livewire.hook !== 'function') {
                    return;
                }
                window.__easyposMenuAvailabilityHooksRegistered = true;

                const emitToggleErrorToast = () => {
                    window.dispatchEvent(new CustomEvent('menu-alert', {
                        detail: {
                            type: 'error',
                            message: 'Could not save availability. Please try again.'
                        }
                    }));
                };

                const payloadHasToggleCall = (payload) => {
                    if (typeof payload !== 'string') {
                        return false;
                    }
                    try {
                        const parsed = JSON.parse(payload);
                        return Array.isArray(parsed?.components) && parsed.components.some(
                            (component) => Array.isArray(component?.calls) && component.calls.some(
                                (call) => call?.method === 'toggleAvailability'
                            )
                        );
                    } catch (_) {
                        return payload.includes('"toggleAvailability"');
                    }
                };

                window.Livewire.hook('request', ({ options, payload, respond, succeed, fail }) => {
                    if (!payloadHasToggleCall(payload)) {
                        return;
                    }

                    const controller = new AbortController();
                    options.signal = controller.signal;

                    const timeoutId = setTimeout(() => controller.abort(), 8000);
                    const onOffline = () => controller.abort();
                    window.addEventListener('offline', onOffline, { once: true });

                    const cleanup = () => {
                        clearTimeout(timeoutId);
                        window.removeEventListener('offline', onOffline);
                    };

                    respond(cleanup);
                    succeed(cleanup);
                    fail(({ status, preventDefault }) => {
                        cleanup();
                        if (status === 503 || status >= 500 || status === 0) {
                            preventDefault();
                        }
                    });
                });

                window.Livewire.hook('commit', ({ commit, fail }) => {
                    const isToggleCommit = Array.isArray(commit?.calls) && commit.calls.some(
                        (call) => call?.method === 'toggleAvailability'
                    );

                    if (!isToggleCommit) {
                        return;
                    }

                    fail(() => {
                        emitToggleErrorToast();
                    });
                });
            };

            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                registerMenuAvailabilityHooks();
            } else {
                document.addEventListener('livewire:init', registerMenuAvailabilityHooks, { once: true });
            }
        })();
    </script>
</x-layout.app-shell>
