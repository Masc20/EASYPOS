@props(['user' => auth()->user()])

@if ($user && !$user->isStrictFloorStaff())
    <nav class="flex items-center gap-5 text-sm font-medium text-brand-text-muted">
        @if ($user->hasRole('owner') || $user->hasRole('super-admin') || $user->hasRole('branch-manager'))
            <a href="{{ url('/') }}"
                class="hover:text-brand-primary dark:hover:text-brand-secondary transition {{ request()->is('/') ? 'text-brand-primary dark:text-brand-secondary font-bold' : '' }}">
                Dashboard
            </a>
        @endif

        @can('pos.access')
            <a href="{{ route('pos') }}"
                class="hover:text-brand-primary dark:hover:text-brand-secondary transition {{ request()->is('pos*') ? 'text-brand-primary dark:text-brand-secondary font-bold' : '' }}">
                Point of Sale
            </a>
        @endcan

        @can('inventory.view')
            <a href="{{ route('inventory') }}"
                class="hover:text-brand-primary dark:hover:text-brand-secondary transition {{ request()->is('inventory*') ? 'text-brand-primary dark:text-brand-secondary font-bold' : '' }}">
                Inventory
            </a>
        @endcan

        @can('kitchen.view')
            <a href="{{ route('kitchen') }}"
                class="hover:text-brand-primary dark:hover:text-brand-secondary transition {{ request()->is('kitchen*') ? 'text-brand-primary dark:text-brand-secondary font-bold' : '' }}">
                Kitchen Display
            </a>
        @endcan

        @if ($user->canAccessPanel(filament()->getPanel('admin')))
            <a href="{{ url('/admin') }}"
                class="inline-flex items-center gap-1 rounded-md bg-brand-primary/10 dark:bg-brand-primary/30 px-2.5 py-1 text-xs font-semibold text-brand-primary dark:text-brand-secondary hover:bg-brand-primary/20 border border-brand-border transition">
                Admin Back-Office &rarr;
            </a>
        @endif
    </nav>
@endif
