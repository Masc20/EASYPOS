@extends('errors.layout')

@php
    $code = isset($exception) ? $exception->getStatusCode() : 300;
@endphp

@section('title', __('HTTP Redirection Notice'))
@section('code', (string) $code)
@section('headline', __('Resource Redirection Required'))
@section('message', __('This terminal route or resource has been relocated. Under normal operation, your web browser automatically follows HTTP 3xx redirection headers to the destination station.'))

@section('icon')
    <svg class="h-10 w-10 text-brand-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
    </svg>
@endsection

@section('action')
    @auth
        <a href="{{ auth()->user()->stationRoute() }}"
            class="inline-flex items-center justify-center gap-2 rounded-md bg-brand-primary hover:bg-brand-primary-hover text-white px-5 py-2.5 text-sm font-semibold transition cursor-pointer shadow-sm">
            <span>Proceed to {{ auth()->user()->stationTitle() }}</span>
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
        </a>
    @else
        <a href="{{ route('login') }}"
            class="inline-flex items-center justify-center gap-2 rounded-md bg-brand-primary hover:bg-brand-primary-hover text-white px-5 py-2.5 text-sm font-semibold transition cursor-pointer shadow-sm">
            <span>Proceed to Login</span>
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
        </a>
    @endauth

    <a href="{{ url('/') }}"
        class="inline-flex items-center justify-center gap-2 rounded-md border border-brand-border bg-brand-card hover:bg-brand-muted text-brand-text px-4 py-2.5 text-sm font-medium transition cursor-pointer shadow-2xs">
        <span>Terminal Home</span>
    </a>
@endsection
