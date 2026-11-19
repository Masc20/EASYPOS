@props(['user' => auth()->user()])

<div class="flex items-center gap-3 text-sm">

    @if ($user)
        <div class="flex items-center gap-2">
            {{-- @if ($user->branch)
                <span
                    class="rounded-md bg-brand-bg border border-brand-border px-2 py-0.5 text-xs font-semibold text-brand-text font-mono">
                    {{ $user->branch->code }}
                </span>
            @endif --}}

            <span class="font-medium text-brand-text">{{ $user->name }}</span>

            @if ($user->emp_id)
                <span
                    class="font-mono text-xs text-brand-text-muted bg-brand-bg px-1.5 py-0.5 rounded border border-brand-border/60">
                    {{ $user->emp_id }}
                </span>
            @endif

            {{-- @foreach ($user->roles as $role)
                <span
                    class="rounded-md bg-brand-secondary/15 border border-brand-border px-2 py-0.5 text-xs font-semibold text-brand-primary dark:text-brand-secondary capitalize">
                    {{ str_replace('-', ' ', $role->name) }}
                </span>
            @endforeach --}}
        </div>

        <form method="POST" action="{{ route('terminal.lock') }}" class="inline"
            x-data="{ locking: false }"
            @submit="if (locking) { $event.preventDefault(); return; } if ($refs.cartStateInput && typeof window.__easyposSerializeCart === 'function') { $refs.cartStateInput.value = window.__easyposSerializeCart() || ''; } locking = true; window.EASYPOS_BEGIN_LOGOUT?.();">
            @csrf
            <input type="hidden" name="return_url" value="{{ request()->getRequestUri() }}">
            <input type="hidden" name="cart_state" x-ref="cartStateInput" value="">
            <button type="submit" :disabled="locking"
                class="rounded-md border border-brand-border bg-brand-card px-3 py-1.5 text-xs font-medium text-brand-text hover:border-brand-primary hover:text-brand-primary transition shadow-2xs cursor-pointer disabled:opacity-60 disabled:cursor-wait">
                <span x-show="!locking">Lock</span>
                <span x-show="locking" x-cloak>Locking...</span>
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="inline"
            x-data="{ loggingOut: false }"
            @submit="if (loggingOut) { $event.preventDefault(); return; } window.__easyposClearSavedCart?.(); loggingOut = true; window.EASYPOS_BEGIN_LOGOUT?.();">
            @csrf
            <button type="submit" :disabled="loggingOut"
                class="rounded-md border border-brand-border bg-brand-card px-3 py-1.5 text-xs font-medium text-brand-text hover:border-brand-error hover:text-brand-error transition shadow-2xs cursor-pointer disabled:opacity-60 disabled:cursor-wait">
                <span x-show="!loggingOut">
                    {{ $user->canAccessAdmin() && !$user->isStrictFloorStaff() ? 'Sign Out' : 'Clock Out' }}
                </span>
                <span x-show="loggingOut" x-cloak>
                    {{ $user->canAccessAdmin() && !$user->isStrictFloorStaff() ? 'Signing out...' : 'Clocking out...' }}
                </span>
            </button>
        </form>
    @else
        @if (!request()->routeIs('login'))
            <a href="{{ route('login') }}"
                class="rounded-md bg-brand-primary hover:bg-brand-primary-hover text-white px-3.5 py-1.5 text-xs font-semibold transition shadow-2xs">
                Floor Staff Sign In
            </a>
        @endif
        <a href="{{ url('/admin/login') }}"
            class="rounded-md border border-brand-border bg-brand-card px-3.5 py-1.5 text-xs font-medium text-brand-text hover:border-brand-secondary hover:text-brand-primary dark:hover:text-brand-secondary transition shadow-2xs">
            Admin Login
        </a>
    @endif
</div>
