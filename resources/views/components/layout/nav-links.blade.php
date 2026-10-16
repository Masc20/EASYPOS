@props(['user' => auth()->user()])

@if ($user)
    <nav class="flex items-center gap-5 text-sm font-medium text-brand-text-muted">
        @if ($user->hasRole('owner') || $user->hasRole('super-admin') || $user->hasRole('branch-manager'))
            <a href="{{ url('/') }}" wire:navigate
                class="hover:text-brand-primary dark:hover:text-brand-secondary transition {{ request()->is('/') ? 'text-brand-primary dark:text-brand-secondary font-bold' : '' }}">
                Dashboard
            </a>
        @endif
        @if ($user->isStrictFloorStaff())
            @can('pos.access')
                <a href="{{ route('pos') }}" wire:navigate
                    class="hover:text-brand-primary dark:hover:text-brand-secondary transition {{ request()->is('pos*') ? 'text-brand-primary dark:text-brand-secondary font-bold' : '' }}">
                    Point of Sale
                </a>
            @endcan

            @can('menu.view')
                <a href="{{ route('menu') }}" wire:navigate
                    class="hover:text-brand-primary dark:hover:text-brand-secondary transition {{ request()->is('menu*') ? 'text-brand-primary dark:text-brand-secondary font-bold' : '' }}">
                    Menu Catalog
                </a>
            @endcan

            @can('kitchen.view')
                <a href="{{ route('kitchen') }}" wire:navigate
                    class="hover:text-brand-primary dark:hover:text-brand-secondary transition {{ request()->is('kitchen*') ? 'text-brand-primary dark:text-brand-secondary font-bold' : '' }}">
                    Kitchen Display
                </a>
            @endcan
        @else
            @if ($user->hasRole('branch-manager'))
                @can('pos.access')
                    <a href="{{ route('pos') }}" wire:navigate
                        class="hover:text-brand-primary dark:hover:text-brand-secondary transition {{ request()->is('pos*') ? 'text-brand-primary dark:text-brand-secondary font-bold' : '' }}">
                        Point of Sale
                    </a>
                @endcan
            @endif

            @if (auth()->guard('web')->check() &&
                    ($user->can('pos.access') || $user->can('menu.view') || $user->can('kitchen.view')))
                <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                    <button @click="open = !open" type="button"
                        class="inline-flex items-center gap-1.5 py-1 hover:text-brand-primary dark:hover:text-brand-secondary transition cursor-pointer {{ request()->is('pos*') || request()->is('menu*') || request()->is('kitchen*') ? 'text-brand-primary dark:text-brand-secondary font-bold' : '' }}">
                        <span>Floor Terminals</span>
                        <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="{ 'rotate-180': open }"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="open" x-cloak
                        class="absolute left-0 mt-2 w-52 rounded-md bg-brand-card border border-brand-border shadow-lg py-1 z-50 animate-in fade-in zoom-in-95 duration-100">
                        <div
                            class="px-3 py-1.5 text-2xs font-bold uppercase tracking-wider text-brand-text-muted border-b border-brand-border/60">
                            Floor Terminals
                        </div>
                        @can('pos.access')
                            <a href="{{ route('pos') }}" wire:navigate
                                class="flex items-center justify-between px-3.5 py-2 text-xs text-brand-text hover:bg-brand-bg hover:text-brand-primary dark:hover:text-brand-secondary transition {{ request()->is('pos*') ? 'font-bold text-brand-primary dark:text-brand-secondary bg-brand-bg/50' : '' }}">
                                <span>Point of Sale</span>
                                <span class="text-2xs text-brand-text-muted font-mono">/pos</span>
                            </a>
                        @endcan
                        @can('menu.view')
                            <a href="{{ route('menu') }}" wire:navigate
                                class="flex items-center justify-between px-3.5 py-2 text-xs text-brand-text hover:bg-brand-bg hover:text-brand-primary dark:hover:text-brand-secondary transition {{ request()->is('menu*') ? 'font-bold text-brand-primary dark:text-brand-secondary bg-brand-bg/50' : '' }}">
                                <span>Menu Catalog</span>
                                <span class="text-2xs text-brand-text-muted font-mono">/menu</span>
                            </a>
                        @endcan
                        @can('kitchen.view')
                            <a href="{{ route('kitchen') }}" wire:navigate
                                class="flex items-center justify-between px-3.5 py-2 text-xs text-brand-text hover:bg-brand-bg hover:text-brand-primary dark:hover:text-brand-secondary transition {{ request()->is('kitchen*') ? 'font-bold text-brand-primary dark:text-brand-secondary bg-brand-bg/50' : '' }}">
                                <span>Kitchen Display</span>
                                <span class="text-2xs text-brand-text-muted font-mono">/kitchen</span>
                            </a>
                        @endcan
                    </div>
                </div>
            @endif
        @endif

        @if ($user->canAccessPanel(filament()->getPanel('admin')))
            <a href="{{ url('/admin') }}"
                class="inline-flex items-center gap-1 rounded-md bg-brand-primary/10 dark:bg-brand-primary/30 px-2.5 py-1 text-xs font-semibold text-brand-primary dark:text-brand-secondary hover:bg-brand-primary/20 border border-brand-border transition">
                Admin Back-Office &rarr;
            </a>
        @endif
    </nav>
@endif
