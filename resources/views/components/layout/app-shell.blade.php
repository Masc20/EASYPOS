<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'EASYPOS') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-gray-100 text-gray-900">
        <header class="border-b border-gray-200 bg-white shadow-xs">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-3">
                <div class="flex items-center gap-8">
                    <a href="{{ url('/') }}" class="text-xl font-bold tracking-tight text-blue-600">
                        {{ config('app.name', 'EASYPOS') }}
                    </a>
                    <nav class="flex items-center gap-5 text-sm font-medium text-gray-600">
                        <a href="{{ url('/') }}" class="hover:text-blue-600 {{ request()->is('/') ? 'text-blue-600 font-semibold' : '' }}">Dashboard</a>
                        <a href="{{ url('/pos') }}" class="hover:text-blue-600 {{ request()->is('pos*') ? 'text-blue-600 font-semibold' : '' }}">Point of Sale</a>
                        <a href="{{ url('/inventory') }}" class="hover:text-blue-600 {{ request()->is('inventory*') ? 'text-blue-600 font-semibold' : '' }}">Inventory</a>
                        @auth
                            @if(auth()->user()->canAccessPanel(filament()->getPanel('admin')))
                                <a href="{{ url('/admin') }}" class="inline-flex items-center gap-1 rounded bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 hover:bg-amber-100 border border-amber-200">
                                    Admin Panel &rarr;
                                </a>
                            @endif
                        @endauth
                    </nav>
                </div>

                <div class="flex items-center gap-3 text-sm">
                    @auth
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-gray-800">{{ auth()->user()->name }}</span>
                            @foreach(auth()->user()->roles as $role)
                                <span class="rounded bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800">{{ $role->name }}</span>
                            @endforeach
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="rounded border border-gray-300 bg-white px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                Sign out
                            </button>
                        </form>
                    @else
                        @if (!request()->routeIs('login'))
                            <a href="{{ route('login') }}" class="rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">
                                Sign in
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl p-6">{{ $slot }}</main>
        @livewireScripts
    </body>
</html>
