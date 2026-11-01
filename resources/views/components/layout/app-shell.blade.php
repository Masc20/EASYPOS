<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'EASYPOS') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="min-h-screen bg-brand-bg text-brand-text font-sans antialiased">
    <!-- Navigation -->
    <x-layout.navbar />

    <!-- Main Viewport Content -->
    <main class="mx-auto max-w-7xl p-6">
        {{ $slot }}
    </main>

    @livewireScripts
    @if(auth()->check() && auth()->user()->isStrictFloorStaff())
        <script>
            // Terminal Page Lock: Trap browser back navigation on active terminal
            (function() {
                history.pushState(null, document.title, location.href);
                window.addEventListener('popstate', function () {
                    history.pushState(null, document.title, location.href);
                });
            })();
        </script>
    @endif
</body>

</html>
