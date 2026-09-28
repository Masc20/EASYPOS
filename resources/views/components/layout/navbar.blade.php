@props(['user' => auth()->user()])

@php
    $logoUrl = $user && $user->isStrictFloorStaff() ? $user->stationRoute() : url('/');
@endphp

<header class="border-b border-brand-border bg-brand-card shadow-2xs transition-colors duration-200">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-3.5">
        <!-- Brand & Station / Navigation Section -->
        <div class="flex items-center gap-6">
            <a href="{{ $logoUrl }}"
                class="flex items-center gap-2.5 text-xl font-bold tracking-tight text-brand-primary dark:text-brand-secondary">
                <span
                    class="flex h-8 w-8 items-center justify-center rounded-md bg-brand-primary text-white font-black text-sm shadow-sm shadow-brand-primary/30">
                    EP
                </span>
                <span>{{ config('app.name', 'EASYPOS') }}</span>
            </a>

            <!-- Dedicated Floor Staff Station Status (No cross-station links) -->
            <x-layout.station-badge :user="$user" />

            <!-- Management / Multi-Role Navigation Links (Permission-gated) -->
            <x-layout.nav-links :user="$user" />
        </div>

        <!-- User Context & Controls -->
        <x-layout.user-nav :user="$user" />
    </div>
</header>
