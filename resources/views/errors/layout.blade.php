<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="{{ request()->cookie('theme') === 'dark' ? 'dark' : '' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code', 'Error') - @yield('title', 'EASYPOS')</title>

    <x-layout.theme-script />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body
    class="min-h-screen bg-brand-bg text-brand-text font-sans antialiased selection:bg-brand-primary selection:text-white flex flex-col justify-between">
    <!-- Top System Header -->
    <header
        class="w-full px-6 py-4 flex items-center justify-between border-b border-brand-border bg-brand-card/60 backdrop-blur-xs">
        <div class="flex items-center gap-3">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5 group">
                <span
                    class="flex h-9 w-9 items-center justify-center rounded-md bg-brand-primary text-white font-black text-sm shadow-sm shadow-brand-primary/30 group-hover:scale-105 transition-transform">
                    EP
                </span>
                <div>
                    <span class="font-bold tracking-tight text-brand-text text-base leading-none block">
                        {{ config('app.name', 'EASYPOS') }}
                    </span>
                    <span class="text-[11px] font-medium text-brand-text-muted tracking-wide uppercase">
                        Terminal System Notice
                    </span>
                </div>
            </a>
        </div>

        <div class="flex items-center gap-3">
            @auth
                <div
                    class="hidden sm:flex items-center gap-2 rounded-md border border-brand-border bg-brand-muted/50 px-3 py-1.5 text-xs text-brand-text-muted">
                    <span class="h-2 w-2 rounded-full bg-brand-success animate-pulse"></span>
                    <span class="font-medium text-brand-text">{{ auth()->user()->name }}</span>
                    <span>&bull;</span>
                    <span class="font-mono">{{ auth()->user()->emp_id ?? 'Staff' }}</span>
                </div>
            @endauth

            <!-- Theme Toggle -->
            <x-ui.theme-toggle />
        </div>
    </header>

    <!-- Main Viewport Content -->
    <main class="w-full flex-1 flex items-center justify-center p-4 sm:p-6 my-auto">
        <div class="w-full max-w-xl mx-auto">
            <div
                class="relative overflow-hidden rounded-xl border border-brand-border bg-brand-card p-6 sm:p-10 shadow-lg text-center">
                <!-- Ambient Subtle Glow Background -->
                <div
                    class="absolute -top-20 -left-20 w-44 h-44 rounded-full bg-brand-primary/10 blur-3xl pointer-events-none">
                </div>
                <div
                    class="absolute -bottom-20 -right-20 w-44 h-44 rounded-full bg-brand-secondary/10 blur-3xl pointer-events-none">
                </div>

                <!-- Icon Container -->
                <div
                    class="relative mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl border border-brand-border bg-brand-muted/80 text-brand-primary dark:text-brand-secondary shadow-inner">
                    @yield('icon')
                </div>

                <!-- Status Code Badge -->
                <div class="mb-3">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-brand-primary/20 bg-brand-primary/10 px-3.5 py-1 text-xs font-mono font-bold tracking-wider text-brand-primary dark:border-brand-accent/30 dark:bg-brand-accent/10 dark:text-brand-accent">
                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                        HTTP @yield('code', 'ERR')
                    </span>
                </div>

                <!-- Error Headline -->
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-brand-text mb-3">
                    @hasSection('headline')
                        @yield('headline')
                    @else
                        @yield('title', 'Terminal Notice')
                    @endif
                </h1>

                <!-- Explanatory Message -->
                <p class="text-sm sm:text-base text-brand-text-muted max-w-md mx-auto leading-relaxed mb-6">
                    @yield('message', 'The requested system operation could not be completed.')
                </p>

                <!-- Detailed Exception Output (if custom message provided) -->
                @php
                    $rawMessage = isset($exception) ? $exception->getMessage() : null;
                    $commonMessages = [
                        '',
                        'Not Found',
                        'Forbidden',
                        'Unauthorized',
                        'Bad Request',
                        'Page Expired',
                        'Too Many Requests',
                        'Server Error',
                        'Service Unavailable',
                    ];
                @endphp
                @if (!empty($rawMessage) && !in_array($rawMessage, $commonMessages) && !str_starts_with($rawMessage, 'http'))
                    <div
                        class="mb-6 mx-auto max-w-md rounded-md border border-brand-border bg-brand-muted/60 p-3 text-left text-xs font-mono text-brand-text-muted">
                        <div class="flex items-center gap-1.5 font-bold uppercase tracking-wider text-brand-text mb-1">
                            <svg class="h-3.5 w-3.5 text-brand-secondary" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Terminal Diagnostic</span>
                        </div>
                        <p class="break-words leading-relaxed text-brand-text">{{ $rawMessage }}</p>
                    </div>
                @endif

                <!-- Action Button Group -->
                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    @hasSection('action')
                        @yield('action')
                    @else
                        @auth
                            <a href="{{ auth()->user()->stationRoute() }}"
                                class="inline-flex items-center justify-center gap-2 rounded-md bg-brand-primary hover:bg-brand-primary-hover text-white px-5 py-2.5 text-sm font-semibold transition cursor-pointer shadow-sm">
                                <span>Return to {{ auth()->user()->stationTitle() }}</span>
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </a>

                            @if (auth()->user()->isOwner() || auth()->user()->can('admin.access'))
                                <a href="{{ url('/admin') }}"
                                    class="inline-flex items-center justify-center gap-2 rounded-md border border-brand-border bg-brand-card hover:border-brand-secondary text-brand-text px-4 py-2.5 text-sm font-medium transition cursor-pointer shadow-2xs">
                                    <span>Admin Panel</span>
                                </a>
                            @endif
                        @else
                            <a href="{{ route('login') }}"
                                class="inline-flex items-center justify-center gap-2 rounded-md bg-brand-primary hover:bg-brand-primary-hover text-white px-5 py-2.5 text-sm font-semibold transition cursor-pointer shadow-sm">
                                <span>Floor Staff Login</span>
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </a>
                        @endauth

                        <button type="button"
                            onclick="window.history.length > 1 ? window.history.back() : window.location.href='{{ url('/') }}'"
                            class="inline-flex items-center justify-center gap-2 rounded-md border border-brand-border bg-brand-card hover:bg-brand-muted text-brand-text px-4 py-2.5 text-sm font-medium transition cursor-pointer shadow-2xs">
                            <svg class="h-4 w-4 text-brand-text-muted" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            <span>Go Back</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </main>

    <!-- Professional Footer -->
    <footer
        class="w-full py-4 px-6 border-t border-brand-border bg-brand-card/40 text-center text-xs text-brand-text-muted">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
            <div>
                <span>&copy; {{ date('Y') }} {{ config('app.name', 'EASYPOS') }} &bull; Multi-Branch Restaurant
                    POS Engine</span>
            </div>
            <div class="flex items-center gap-3 text-[11px]">
                @auth
                    <span>Branch: <strong
                            class="text-brand-text font-mono">{{ auth()->user()->branch?->code ?? (auth()->user()->branch_code ?? 'HQ') }}</strong></span>
                    <span>&bull;</span>
                    <span>Terminal: <strong class="text-brand-text">{{ auth()->user()->stationTitle() }}</strong></span>
                    <span>&bull;</span>
                @endauth
                <span class="font-mono">{{ now()->format('Y-m-d H:i') }}</span>
            </div>
        </div>
    </footer>
</body>

</html>
