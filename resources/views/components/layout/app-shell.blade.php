<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'EASYPOS') }}</title>
    <script>
        document.documentElement.classList.remove('dark');
    </script>

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
    <script>
        (function () {
            if (window.filamentErrorNotifications) {
                delete window.filamentErrorNotifications;
            }

            window.__easyposLoggingOut = false;
            window.__easyposActiveWireControllers = window.__easyposActiveWireControllers || new Set();

            window.EASYPOS_BEGIN_LOGOUT = function () {
                window.__easyposLoggingOut = true;
                window.__easyposActiveWireControllers.forEach((ctrl) => {
                    try { ctrl.abort(); } catch (_) {}
                });
                window.__easyposActiveWireControllers.clear();
            };

            const registerGlobalRequestHook = () => {
                if (window.__easyposGlobalRequestHookRegistered || !window.Livewire || typeof window.Livewire.hook !== 'function') {
                    return;
                }
                window.__easyposGlobalRequestHookRegistered = true;

                window.Livewire.hook('request', ({ options, respond, succeed, fail }) => {
                    const controller = new AbortController();
                    const existingSignal = options.signal;

                    if (existingSignal) {
                        if (existingSignal.aborted) {
                            controller.abort();
                        } else {
                            existingSignal.addEventListener('abort', () => controller.abort(), { once: true });
                        }
                    }
                    options.signal = controller.signal;

                    if (window.__easyposLoggingOut) {
                        controller.abort();
                    }

                    window.__easyposActiveWireControllers.add(controller);
                    const cleanup = () => window.__easyposActiveWireControllers.delete(controller);

                    respond(cleanup);
                    succeed(cleanup);
                    fail(({ status, preventDefault }) => {
                        cleanup();
                        if (window.__easyposLoggingOut) {
                            preventDefault();
                            return;
                        }
                        if (status === 423) {
                            preventDefault();
                            window.location.href = @json(route('terminal.lock.show'));
                        }
                    });
                });
            };

            if (window.Livewire) {
                registerGlobalRequestHook();
            } else {
                document.addEventListener('livewire:init', registerGlobalRequestHook, { once: true });
            }
        })();
    </script>
    @auth
        <script>
            // Terminal Page Guard: Trap browser back navigation & reload bfcache snapshots
            (function () {
                history.pushState(null, document.title, location.href);
                window.addEventListener('popstate', function () {
                    history.pushState(null, document.title, location.href);
                });
                window.addEventListener('pageshow', function (event) {
                    if (event.persisted) {
                        window.location.reload();
                    }
                });
            })();
        </script>
    @endauth
</body>

</html>
