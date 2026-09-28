@extends('errors.layout')

@section('title', __('Unauthorized'))
@section('code', '401')
@section('headline', __('Staff Authentication Required'))
@section('message', __('Access to this terminal function requires an active staff session. Please authenticate with your employee PIN or credentials.'))

@section('icon')
    <svg class="h-10 w-10 text-brand-primary dark:text-brand-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
    </svg>
@endsection

@section('action')
    <a href="{{ route('login') }}"
        class="inline-flex items-center justify-center gap-2 rounded-md bg-brand-primary hover:bg-brand-primary-hover text-white px-5 py-2.5 text-sm font-semibold transition cursor-pointer shadow-sm">
        <span>Go to Staff Login</span>
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
        </svg>
    </a>
    <button type="button"
        onclick="window.history.length > 1 ? window.history.back() : window.location.href='{{ url('/') }}'"
        class="inline-flex items-center justify-center gap-2 rounded-md border border-brand-border bg-brand-card hover:bg-brand-muted text-brand-text px-4 py-2.5 text-sm font-medium transition cursor-pointer shadow-2xs">
        <svg class="h-4 w-4 text-brand-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        <span>Go Back</span>
    </button>
@endsection
