@props(['user' => auth()->user()])

<div class="flex items-center gap-3 text-sm">
    <!-- Theme Toggle Button (Light / Dark) -->
    <x-ui.theme-toggle class="mr-1" />

    @if ($user)
        <div class="flex items-center gap-2">
            @if ($user->branch)
                <span
                    class="rounded-md bg-brand-bg border border-brand-border px-2 py-0.5 text-xs font-semibold text-brand-text font-mono">
                    {{ $user->branch->code }}
                </span>
            @endif

            <span class="font-medium text-brand-text">{{ $user->name }}</span>

            @if ($user->emp_id)
                <span
                    class="font-mono text-xs text-brand-text-muted bg-brand-bg px-1.5 py-0.5 rounded border border-brand-border/60">
                    {{ $user->emp_id }}
                </span>
            @endif

            @foreach ($user->roles as $role)
                <span
                    class="rounded-md bg-brand-secondary/15 border border-brand-border px-2 py-0.5 text-xs font-semibold text-brand-primary dark:text-brand-secondary capitalize">
                    {{ str_replace('-', ' ', $role->name) }}
                </span>
            @endforeach
        </div>

        <form method="POST" action="{{ route('logout') }}" class="inline">
            @csrf
            <button type="submit"
                class="rounded-md border border-brand-border bg-brand-card px-3 py-1.5 text-xs font-medium text-brand-text hover:border-brand-error hover:text-brand-error transition shadow-2xs cursor-pointer">
                Clock Out
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
            Owner Login
        </a>
    @endif
</div>
