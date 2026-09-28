<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="h-full {{ request()->cookie('theme') === 'dark' ? 'dark' : '' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'EASYPOS') }} - Floor Staff Terminal</title>

    <!-- Instant Anti-Flash Theme Engine -->
    <x-layout.theme-script />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body
    class="h-full bg-brand-bg text-brand-text font-sans antialiased selection:bg-brand-primary selection:text-white flex flex-col justify-between">
    <!-- Minimalist Kiosk Top Branding -->
    <header
        class="w-full px-6 py-4 flex items-center justify-between border-b border-brand-border bg-brand-bg/90 backdrop-blur-xs">
        <div class="flex items-center gap-2.5">
            <span
                class="flex h-9 w-9 items-center justify-center rounded-md bg-brand-primary text-white font-black text-sm shadow-sm shadow-brand-primary/30">
                EP
            </span>
            <div>
                <span
                    class="font-bold tracking-tight text-brand-text text-base leading-none block">{{ config('app.name', 'EASYPOS') }}</span>
                <span class="text-[11px] font-medium text-brand-text-muted tracking-wide uppercase">Point of Sale
                    Terminal</span>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <!-- Theme Toggle Button (Light / Dark) -->
            <x-ui.theme-toggle />

            <span
                class="inline-flex items-center gap-1.5 rounded-full bg-brand-success/10 border border-brand-success/30 px-3 py-1 text-xs font-semibold text-brand-success">
                <span class="h-2 w-2 rounded-full bg-brand-success animate-pulse"></span>
                Terminal Online
            </span>
            <a href="{{ url('/admin/login') }}"
                class="rounded-md border border-brand-border bg-brand-card px-3.5 py-1.5 text-xs font-semibold text-brand-text hover:border-brand-secondary hover:text-brand-primary dark:hover:text-brand-secondary transition shadow-2xs">
                Back-Office Portal &rarr;
            </a>
        </div>
    </header>

    <!-- Main Kiosk Viewport -->
    <main class="w-full flex-1 flex items-center justify-center p-4 sm:p-6">
        {{ $slot }}
    </main>

    <!-- Kiosk Footer Note -->
    <footer class="w-full py-4 text-center text-xs text-brand-text-muted">
        <span>&copy; {{ date('Y') }} {{ config('app.name', 'EASYPOS') }} &bull; Centralized Multi-Branch POS
            System</span>
    </footer>

    @livewireScripts
</body>

</html>
