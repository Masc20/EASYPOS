<div x-data="{ noticeTitle: 'Notice', noticeMessage: '' }"
    x-on:show-notice-modal.window="
        noticeTitle = $event.detail?.title || 'Notice';
        noticeMessage = $event.detail?.message || '';
        $dispatch('open-modal', 'notice-modal');
    ">
    <x-ui.modal name="notice-modal" maxWidth="sm">
        <div class="flex items-start gap-3" role="alert">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-amber-500/15 text-amber-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v3m0 4h.01M10.3 3.86 1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.7 3.86a2 2 0 00-3.4 0z" />
                </svg>
            </div>
            <div class="min-w-0">
                <h2 class="text-sm font-bold text-brand-text" x-text="noticeTitle"></h2>
                <p class="mt-1 text-sm leading-relaxed text-brand-text-muted" x-text="noticeMessage"></p>
            </div>
        </div>

        <div class="mt-5 flex justify-end border-t border-brand-border pt-4">
            <x-ui.button type="button" x-on:click="$dispatch('close-modal', 'notice-modal')">
                Okay
            </x-ui.button>
        </div>
    </x-ui.modal>
</div>
