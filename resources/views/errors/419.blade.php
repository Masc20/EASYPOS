@extends('errors.layout')

@section('title', __('Page Expired'))
@section('code', '419')
@section('headline', __('Terminal Session Expired'))
@section('message', __('Your CSRF security token or terminal session timed out due to inactivity. Please reload the page to refresh your security token and resume operations.'))

@section('icon')
    <svg class="h-10 w-10 text-brand-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
@endsection

@section('action')
    <button type="button"
        onclick="window.location.reload()"
        class="inline-flex items-center justify-center gap-2 rounded-md bg-brand-primary hover:bg-brand-primary-hover text-white px-5 py-2.5 text-sm font-semibold transition cursor-pointer shadow-sm">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        <span>Reload &amp; Resume Session</span>
    </button>
    <a href="{{ route('login') }}"
        class="inline-flex items-center justify-center gap-2 rounded-md border border-brand-border bg-brand-card hover:bg-brand-muted text-brand-text px-4 py-2.5 text-sm font-medium transition cursor-pointer shadow-2xs">
        <span>Re-authenticate</span>
    </a>
@endsection
