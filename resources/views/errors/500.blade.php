@extends('errors.layout')

@section('title', __('Server Error'))
@section('code', '500')
@section('headline', __('Internal Server Exception'))
@section('message', __('An unexpected server error occurred while processing this request. The system has automatically recorded this incident in the diagnostic logs.'))

@section('icon')
    <svg class="h-10 w-10 text-brand-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
    </svg>
@endsection

@section('action')
    <button type="button"
        onclick="window.location.reload()"
        class="inline-flex items-center justify-center gap-2 rounded-md bg-brand-primary hover:bg-brand-primary-hover text-white px-5 py-2.5 text-sm font-semibold transition cursor-pointer shadow-sm">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        <span>Retry Operation</span>
    </button>
    @auth
        <a href="{{ auth()->user()->stationRoute() }}"
            class="inline-flex items-center justify-center gap-2 rounded-md border border-brand-border bg-brand-card hover:bg-brand-muted text-brand-text px-4 py-2.5 text-sm font-medium transition cursor-pointer shadow-2xs">
            <span>Return to Station</span>
        </a>
    @else
        <a href="{{ url('/') }}"
            class="inline-flex items-center justify-center gap-2 rounded-md border border-brand-border bg-brand-card hover:bg-brand-muted text-brand-text px-4 py-2.5 text-sm font-medium transition cursor-pointer shadow-2xs">
            <span>Welcome Screen</span>
        </a>
    @endauth
@endsection
