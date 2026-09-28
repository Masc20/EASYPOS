<div @if (! $open) hidden @endif class="rounded-md border border-brand-border bg-brand-card text-brand-text shadow-sm p-5">{{ $slot ?? '' }}</div>
