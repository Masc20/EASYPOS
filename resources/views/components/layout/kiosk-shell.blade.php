<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'EASYPOS') }} - Floor Staff Terminal</title>
    <script>
        document.documentElement.classList.remove('dark');
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body
    class="min-h-dvh w-full bg-brand-bg text-brand-text font-sans antialiased selection:bg-brand-primary selection:text-white flex flex-col justify-between relative overflow-x-hidden select-none">

    {{-- Atmospheric Background Layers --}}
    <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
        {{-- Top-Right Atmospheric Glow --}}
        <img src="{{ asset('image/glow.svg') }}" alt=""
            class="pointer-events-none absolute -top-24 -right-24 w-[65vw] max-w-[680px] opacity-80 select-none" />
        <div class="pointer-events-none absolute -top-[15%] -right-[10%] w-[55vw] max-w-[680px] aspect-square rounded-full"
            style="background: radial-gradient(circle, color-mix(in srgb, var(--color-primary) 22%, transparent) 0%, transparent 70%);">
        </div>

        {{-- Bottom-Left Atmospheric Glow --}}
        <img src="{{ asset('image/glow.svg') }}" alt=""
            class="pointer-events-none absolute -bottom-28 -left-28 w-[70vw] max-w-[740px] opacity-90 select-none" />
        <div class="pointer-events-none absolute -bottom-[20%] -left-[10%] w-[60vw] max-w-[740px] aspect-square rounded-full"
            style="background: radial-gradient(circle, color-mix(in srgb, var(--color-primary) 28%, transparent) 0%, transparent 70%);">
        </div>

        {{-- Culinary Illustration Asset Clusters (bg.svg) --}}
        <img src="{{ asset('image/bg.svg') }}" alt=""
            class="pointer-events-none absolute -bottom-10 -left-10 w-[44vw] max-w-[420px] opacity-45 select-none transform origin-bottom-left" />
        <img src="{{ asset('image/bg.svg') }}" alt=""
            class="pointer-events-none absolute -top-8 -right-8 w-[36vw] max-w-[340px] opacity-45 select-none transform origin-top-right rotate-180 sm:rotate-0" />
    </div>

    <!-- Top Navigation Bar -->
    <header
        class="w-full h-12 sm:h-13 px-4 sm:px-6 flex items-center justify-between border-b border-brand-border bg-brand-bg/90 backdrop-blur-xs relative z-20 shrink-0">
        {{-- Left: Branding Box --}}
        <div class="flex items-center gap-2.5">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5 group">
                <span
                    class="flex h-7 w-7 items-center justify-center rounded-md bg-brand-card border border-brand-border shadow-2xs group-hover:border-brand-primary/40 transition">
                    <img src="{{ asset('image/PSB.svg') }}" alt="EASYPOS" class="w-4.5 h-4.5 object-contain" />
                </span>
                <div>
                    <span
                        class="font-bold tracking-tight text-brand-text text-sm leading-tight block">{{ config('app.name', 'EASYPOS') }}</span>
                    <span
                        class="text-[10px] sm:text-[11px] font-medium text-brand-text-muted tracking-wide block leading-tight">Floor
                        Staff Terminal</span>
                </div>
            </a>
        </div>

        {{-- Right: Actions & Status Group --}}
        <div class="flex items-center gap-2 sm:gap-3">

            {{-- Terminal Online Status Pill Badge --}}
            <span
                class="inline-flex items-center gap-1.5 rounded-full bg-brand-success/10 border border-brand-success/30 px-2.5 py-0.5 sm:py-1 text-[11px] font-semibold text-brand-success shadow-2xs whitespace-nowrap">
                <span class="h-2 w-2 rounded-full bg-brand-success animate-pulse"></span>
                Terminal Online
            </span>

            {{-- Back-Office Portal Switch Button --}}
            <a href="{{ url('/admin/login') }}"
                class="rounded-md border border-brand-border bg-brand-card px-2.5 sm:px-3 py-1 text-[11px] font-semibold text-brand-text hover:bg-brand-muted hover:border-brand-primary/30 transition shadow-2xs whitespace-nowrap">
                Back-Office Portal &rarr;
            </a>
        </div>
    </header>

    <!-- Main Kiosk Viewport -->
    <main class="w-full flex-1 flex items-center justify-center px-4 py-3 sm:py-4 relative z-10">
        {{ $slot }}
    </main>

    <!-- Bottom Footer -->
    <footer class="w-full py-2 text-center text-[10px] sm:text-[11px] text-brand-text-muted relative z-20 shrink-0">
        <span>&copy; {{ date('Y') }} {{ config('app.name', 'EASYPOS') }} &middot; Secure Multi-Branch POS System</span>
    </footer>

    @livewireScripts
</body>

</html>
