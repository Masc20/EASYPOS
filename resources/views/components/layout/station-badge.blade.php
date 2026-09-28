@props(['user' => auth()->user()])

@if ($user && $user->isStrictFloorStaff())
    <div class="hidden sm:flex items-center border-l border-brand-border pl-4">
        <span class="inline-flex items-center gap-1.5 rounded-md bg-brand-primary/10 text-brand-primary dark:text-brand-secondary border border-brand-border/70 px-2.5 py-1 text-xs font-bold tracking-wide">
            <span class="h-2 w-2 rounded-full bg-brand-success animate-pulse"></span>
            {{ $user->stationTitle() }}
        </span>
    </div>
@endif
